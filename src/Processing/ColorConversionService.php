<?php

declare(strict_types=1);

namespace wwaz\ColorService\Processing;

use wwaz\Colormodel\Model\Color as ColorModel;
use wwaz\Colormodel\Model\RGB as ModelRGB;
use wwaz\Colorname\ColornameService;
use wwaz\ColorService\Color\CMYK;
use wwaz\ColorService\Color\RGB;
use wwaz\ColorService\DTO\ICCConversionResultDTO;
use wwaz\ColorService\DTO\ICCConversionResultV1;
use wwaz\ColorService\DTO\ICCConversionResultV2;

/**
 * Converts incoming color models into ICC-aware RGB/CMYK result DTOs.
 *
 * @phpstan-type GivenShape array{colorSpace: string, representation: string, value: string}
 * @phpstan-type ProfileNameShape array{rgb: ?string, cmyk: ?string}
 * @phpstan-type ColorObjectMap array<string, object|string|int|float|null>
 * @phpstan-type StringifiedColorMap array<string, string|int|float|null>
 * @phpstan-type V1ResultShape array{
 *     colorWorkSpace: ProfileNameShape,
 *     intent: string,
 *     given: GivenShape,
 *     values: StringifiedColorMap,
 *     perception: StringifiedColorMap,
 *     profiled: StringifiedColorMap,
 *     inField: StringifiedColorMap,
 *     name: string,
 *     conversionStack: list<string>
 * }
 * @phpstan-type V2ResultShape array{
 *     given: GivenShape,
 *     hex: string,
 *     rgb: string,
 *     cmyk: string,
 *     perception: string,
 *     name: string,
 *     schemes?: array<string, list<array<string, string>>>
 * }
 */
class ColorConversionService
{
    protected ColorModel $color;

    /** @var list<string> */
    protected array $conversionStack = [];

    protected float $time = 0.0;

    protected IccColorConverter $iccColorConverter;

    protected ColornameService $colornameService;

    protected bool $includeSchemes = false;

    protected ?ModelRGB $cachedSchemeBaseRgb = null;

    protected string $acceptICCConversionDTO = '';

    /**
     * @param ColorModel $color Input color in RGB, CMYK, or HEX representation.
     */
    public function __construct(
        ColorModel $color,
        ?ColornameService $colornameService = null,
        ?IccColorConverter $iccColorConverter = null,
    ) {
        $this->colornameService = $colornameService ?? new ColornameService();
        $this->iccColorConverter = $iccColorConverter ?? new IccColorConverter();
        $this->conversionStackStart('init');
        $this->color = $color;
    }

    /**
     * Selects the DTO version returned by convert(); pass "v1" for the legacy payload.
     */
    public function setAcceptICCConversionDTO(string|null $version): self
    {
        $this->acceptICCConversionDTO = is_string($version) ? $version : '';

        return $this;
    }

    /**
     * Enables or disables scheme generation in V2 conversion results.
     */
    public function withIncludeSchemes(bool $includeSchemes = true): self
    {
        $this->includeSchemes = $includeSchemes;

        return $this;
    }

    /**
     * Configures RGB/CMYK ICC profiles and converts the current color.
     */
    public function profiled(
        string|object|null $rgbProfile,
        string|object|null $cmykProfile,
        bool $includeSchemes = false,
    ): ICCConversionResultDTO {
        $this->includeSchemes = $includeSchemes;
        $this->setProfile('rgb', $rgbProfile);
        $this->setProfile('cmyk', $cmykProfile);

        return $this->convert();
    }

    /**
     * Assigns or clears the ICC profile for a color space.
     *
     * @param IccColorConverter::COLORSPACE_RGB|IccColorConverter::COLORSPACE_CMYK $colorSpace
     */
    public function setProfile(string $colorSpace, object|string|null $objOrFilename): bool
    {
        return $this->iccColorConverter->setProfile($colorSpace, $objOrFilename);
    }

    /**
     * Returns the configured ICC profile for a color space.
     *
     * @param IccColorConverter::COLORSPACE_RGB|IccColorConverter::COLORSPACE_CMYK $colorSpace
     */
    public function getProfile(string $colorSpace): ?object
    {
        return $this->iccColorConverter->getProfile($colorSpace);
    }

    /**
     * Sets the rendering intent for ICC conversions.
     */
    public function setIntent(?string $intent): self
    {
        $this->iccColorConverter->setIntent($intent);

        return $this;
    }

    /**
     * Returns the normalized rendering intent.
     */
    public function getIntent(): string
    {
        return $this->iccColorConverter->getIntent();
    }

    /**
     * Converts the configured input color into the selected conversion result DTO.
     */
    public function convert(): ICCConversionResultDTO
    {
        $this->conversionStackAdd('convert_start');

        return match ($this->color->type()) {
            'rgb'  => $this->fromRGB($this->color),
            'cmyk' => $this->fromCMYK($this->color),
            'hex'  => $this->fromHEX($this->color),
            default => throw new \InvalidArgumentException('Color type "' . $this->color->type() . '" is unknown'),
        };
    }

    /**
     * Builds color schemes based on the current color or its profiled perception.
     *
     * @return array<string, list<array<string, string>>>
     */
    public function schemes(bool $profiled = false): array
    {
        return (new SchemeCreator(
            $this->color,
            $this->iccColorConverter,
            $this->cachedSchemeBaseRgb,
        ))->create($profiled);
    }

    /**
     * Converts HEX input by normalizing it to RGB first.
     */
    protected function fromHEX(ColorModel $hex): ICCConversionResultDTO
    {
        return $this->fromRGB(new RGB($hex->toRGB()->toString()));
    }

    /**
     * Converts RGB input into profiled CMYK values and perception data.
     */
    protected function fromRGB(RGB $rgb): ICCConversionResultDTO
    {
        $cmykProfiled = $this->rgb2cmykProfiled($rgb);
        $cmykMath     = $rgb->toCmyk();
        $perceptionRgb = $this->cmyk2rgbProfiled($cmykProfiled);

        $this->cacheSchemeBaseRgb($perceptionRgb);

        return $this->returnResult(
            values: [
                'rgb'       => $rgb,
                'hex'       => $rgb->toHex(),
                'cmyk'      => $cmykProfiled,
                'cmyk_math' => $cmykMath,
            ],
            perception: [
                'cmyk' => $cmykProfiled,
                'rgb'  => $perceptionRgb,
                'hex'  => $perceptionRgb->toHex(),
            ],
            profiled: [
                'rgb'  => $rgb,
                'hex'  => $rgb->toHex(),
                'cmyk' => $cmykProfiled,
            ],
            // inField: [
            //     'rgb'  => $rgb,
            //     'cmyk' => $cmykMath,
            // ],
            name: $this->colornameService->fromRgb($perceptionRgb),
        );
    }

    /**
     * Converts CMYK input into profiled RGB perception data.
     */
    protected function fromCMYK(CMYK $cmyk): ICCConversionResultDTO
    {
        $perceptionRgb = $this->cmyk2rgbProfiled($cmyk);
        $valuesRgb     = $perceptionRgb->toRgb();
        $valuesCmyk    = $cmyk->toCmykInt();

        $this->cacheSchemeBaseRgb($perceptionRgb);

        return $this->returnResult(
            values: [
                'rgb'  => $valuesRgb,
                'hex'  => $valuesRgb->toHex(),
                'cmyk' => $valuesCmyk,
            ],
            perception: [
                'rgb' => $perceptionRgb,
                'hex' => $perceptionRgb->toHex(),
            ],
            profiled: [
                'rgb'  => $perceptionRgb,
                'hex'  => $perceptionRgb->toHex(),
                'cmyk' => $valuesCmyk,
            ],
            // inField: [
            //     'rgb'  => $valuesRgb,
            //     'cmyk' => $valuesCmyk,
            // ],
            name: $this->colornameService->fromRgb($perceptionRgb),
        );
    }

    /**
     * Selects and creates the requested DTO version from normalized conversion data.
     *
     * @param ColorObjectMap $values
     * @param ColorObjectMap $perception
     * @param ColorObjectMap $profiled
     * @param ColorObjectMap $inField
     */
    protected function returnResult(
        array $values,
        array $perception,
        array $profiled,
        // array $inField,
        string $name,
    ): ICCConversionResultDTO {
        $v1Data = $this->buildV1ResultData($values, $perception, $profiled, $inField, $name);
        $v2Data = $this->buildV2ResultData($values, $perception, $name);

        if (strtolower($this->acceptICCConversionDTO) === 'v1') {
            return new ICCConversionResultV1($v1Data);
        }

        return new ICCConversionResultV2($v2Data);
    }

    /**
     * Builds the legacy V1 payload including diagnostic conversion metadata.
     *
     * @param ColorObjectMap $values
     * @param ColorObjectMap $perception
     * @param ColorObjectMap $profiled
     * @param ColorObjectMap $inField
     * @return V1ResultShape
     */
    protected function buildV1ResultData(
        array $values,
        array $perception,
        array $profiled,
        // array $inField,
        string $name,
    ): array {
        return [
            'colorWorkSpace' => $this->profileNames(),
            'intent' => $this->getIntent(),
            'given' => $this->givenData(),
            'values' => $this->stringifyColorValues($values),
            'perception' => $this->stringifyColorValues($perception),
            'profiled' => $this->stringifyColorValues($profiled),
            // 'inField' => $this->stringifyColorValues($inField),
            'name' => $name,
            'conversionStack' => $this->conversionStack(),
        ];
    }

    /**
     * Builds the slim V2 payload used by default API responses.
     *
     * @param ColorObjectMap $values
     * @param ColorObjectMap $perception
     * @return V2ResultShape
     */
    protected function buildV2ResultData(array $values, array $perception, string $name): array
    {
        $data = [
            'given' => $this->givenData(),
            'hex' => $values['hex']->toString(),
            'rgb' => $values['rgb']->toString(),
            'cmyk' => $values['cmyk']->toString(),
            'perception' => $perception['hex']->toString(),
            'name' => $name,
            'isProfiled' => $this->profileNames()['cmyk'] ? true : false,
        ];

        if ($this->includeSchemes) {
            $data['schemes'] = $this->schemes();
        }

        return $data;
    }

    /**
     * Returns the original input metadata shared by all DTO formats.
     *
     * @return GivenShape
     */
    protected function givenData(): array
    {
        return [
            'colorSpace' => $this->color->colorSpace(),
            'representation' => $this->color->type(),
            'value' => $this->color->toString(),
        ];
    }

    /**
     * Returns the names of the currently configured ICC profiles.
     *
     * @return ProfileNameShape
     */
    protected function profileNames(): array
    {
        return [
            'rgb' => $this->getProfile(IccColorConverter::COLORSPACE_RGB)?->name(),
            'cmyk' => $this->getProfile(IccColorConverter::COLORSPACE_CMYK)?->name(),
        ];
    }

    /**
     * Converts color model objects to their string representation for DTO arrays.
     *
     * @param ColorObjectMap $values
     * @return StringifiedColorMap
     */
    protected function stringifyColorValues(array $values): array
    {
        $result = [];

        foreach ($values as $key => $value) {
            $result[$key] = is_object($value) ? $value->toString() : $value;
        }

        return $result;
    }

    /**
     * Stores the RGB perception used as base for optional scheme generation.
     */
    protected function cacheSchemeBaseRgb(RGB $rgb): void
    {
        $this->cachedSchemeBaseRgb = new ModelRGB($rgb->toArray());
    }

    /**
     * Converts CMYK to RGB through the configured ICC converter.
     */
    protected function cmyk2rgbProfiled(CMYK $cmyk): RGB
    {
        return $this->iccColorConverter->cmykToRgb($cmyk);
    }

    /**
     * Converts RGB to CMYK through the configured ICC converter and records profiled work.
     */
    protected function rgb2cmykProfiled(RGB $rgb): CMYK
    {
        if ($this->getProfile('cmyk')) {
            $this->conversionStackAdd('rgb2cmyk');
        }

        return $this->iccColorConverter->rgbToCmyk($rgb);
    }

    /**
     * Starts conversion timing and records the first stack entry.
     */
    protected function conversionStackStart(string $step): void
    {
        $this->time = microtime(true);
        $this->conversionStackAdd($step);
    }

    /**
     * Adds a conversion trace entry with elapsed time since stack start.
     */
    protected function conversionStackAdd(string $step): void
    {
        $elapsed = $this->time ? microtime(true) - $this->time : 0;
        $this->conversionStack[] = $step . ' | ' . $elapsed;
    }

    /**
     * @return list<string>
     */
    public function conversionStack(): array
    {
        return $this->conversionStack;
    }
}
