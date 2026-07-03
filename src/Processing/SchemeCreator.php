<?php

declare(strict_types=1);

namespace wwaz\ColorService\Processing;

use wwaz\Colormodel\Model\Color as ColorModel;
use wwaz\Colormodel\Model\RGB as ModelRGB;
use wwaz\Colormodel\Scheme\Analogous;
use wwaz\Colormodel\Scheme\Complementary;
use wwaz\Colormodel\Scheme\Square;
use wwaz\Colormodel\Scheme\Tetradic;
use wwaz\Colormodel\Scheme\Triadic;
use wwaz\ColorService\Color\CMYK;
use wwaz\ColorService\Color\RGB;

/**
 * Builds RGB/CMYK/HEX color scheme swatches from a color model.
 *
 * @phpstan-type SchemeSwatch array{cmyk: string, hex: string, rgb: string, rgb_math: string, given?: string}
 * @phpstan-type SchemeMap array<string, array<int|string, SchemeSwatch>>
 * @phpstan-type RgbMap array<int|string, ModelRGB>
 */
class SchemeCreator
{
    private const SCHEME_STEPS = 10;

    private const SCHEMES_WITH_BASE_AT_ZERO = [
        'complementary',
        'square',
        'analogous',
        'tetradic',
        'triadic',
    ];

    private const CONTINUOUS_SCHEMES = [
        'hue',
        'tint',
        'shade',
        'tone',
    ];

    private const SCHEME_ORDER = [
        'complementary',
        'square',
        'analogous',
        'tetradic',
        'tone',
        'triadic',
        'shade',
        'tint',
        'hue',
    ];

    /** @var array<string, class-string> */
    private const DISCRETE_SCHEME_CLASSES = [
        'complementary' => Complementary::class,
        'square' => Square::class,
        'analogous' => Analogous::class,
        'tetradic' => Tetradic::class,
        'triadic' => Triadic::class,
    ];

    /**
     * @param ColorModel $color Input color used as scheme source.
     * @param IccColorConverter $iccColorConverter Converter shared with the parent conversion service.
     * @param ModelRGB|null $cachedSchemeBaseRgb Optional profiled perception RGB from a prior conversion.
     */
    public function __construct(
        private readonly ColorModel $color,
        private readonly IccColorConverter $iccColorConverter,
        private readonly ?ModelRGB $cachedSchemeBaseRgb = null,
    ) {}

    /**
     * Creates all supported color schemes.
     *
     * @return SchemeMap
     */
    public function create(bool $profiled = false): array
    {
        $schemeBaseRgb = $this->cachedSchemeBaseRgb ?? $this->resolveSchemeBaseRgb();
        $useProfiled   = $this->shouldProfileSchemes($profiled);

        return $this->finalizeSchemes($this->buildSchemes($schemeBaseRgb, $useProfiled));
    }

    /**
     * Determines whether scheme swatches should use ICC-profiled values.
     */
    private function shouldProfileSchemes(bool $profiled = false): bool
    {
        return $profiled || (
            $this->getProfile(IccColorConverter::COLORSPACE_RGB)
            && $this->getProfile(IccColorConverter::COLORSPACE_CMYK)
        );
    }

    /**
     * Returns the configured ICC profile for a color space.
     *
     * @param IccColorConverter::COLORSPACE_RGB|IccColorConverter::COLORSPACE_CMYK $colorSpace
     */
    private function getProfile(string $colorSpace): ?object
    {
        return $this->iccColorConverter->getProfile($colorSpace);
    }

    /**
     * Applies final adjustments to the generated scheme map.
     *
     * @param SchemeMap $schemes
     * @return SchemeMap
     */
    private function finalizeSchemes(array $schemes): array
    {
        return $this->applyGivenCmykToBaseSwatches($schemes);
    }

    /**
     * Replaces base swatches with the exact given input values where applicable.
     *
     * @param SchemeMap $schemes
     * @return SchemeMap
     */
    private function applyGivenCmykToBaseSwatches(array $schemes): array
    {
        $baseSwatch = $this->createBaseSwatchFromGiven();

        if ($baseSwatch === null) {
            return $schemes;
        }

        foreach (array_merge(self::SCHEMES_WITH_BASE_AT_ZERO, self::CONTINUOUS_SCHEMES) as $name) {
            if (isset($schemes[$name][0])) {
                $schemes[$name][0] = $baseSwatch;
            }
        }

        return $schemes;
    }

    /**
     * Creates the base swatch that preserves the originally supplied color.
     *
     * @return SchemeSwatch|null
     */
    private function createBaseSwatchFromGiven(): ?array
    {
        $swatch = match (true) {
            $this->color->type() === 'cmyk' && $this->color instanceof CMYK => $this->formatSchemeColorFromRgb(
                new ModelRGB($this->cmyk2rgbProfiled($this->color)->toArray()),
                $this->color,
            ),
            $this->color->type() === 'rgb' && $this->color instanceof RGB => $this->createBaseSwatchFromProfiledRgb($this->color),
            $this->color->type() === 'hex' => $this->createBaseSwatchFromProfiledRgb(
                new RGB($this->color->toRGB()->toString()),
            ),
            default => null,
        };

        if ($swatch === null) {
            return null;
        }

        $swatch['given'] = $this->givenValueForBaseSwatch();

        return $swatch;
    }

    /**
     * Returns the display value used to mark the original base swatch.
     */
    private function givenValueForBaseSwatch(): string
    {
        return match ($this->color->type()) {
            'hex' => ltrim($this->color->toRGB()->toHex()->toString(), '#'),
            default => $this->color->toString(),
        };
    }

    /**
     * Builds a base swatch from RGB after applying the configured ICC round trip.
     *
     * @return SchemeSwatch
     */
    private function createBaseSwatchFromProfiledRgb(RGB $rgb): array
    {
        $cmykProfiled  = $this->rgb2cmykProfiled($rgb);
        $perceptionRgb   = $this->cmyk2rgbProfiled($cmykProfiled);

        return $this->formatSchemeColorFromRgb(
            new ModelRGB($perceptionRgb->toArray()),
            $cmykProfiled,
        );
    }

    /**
     * Resolves the RGB value used as the source for all schemes.
     */
    private function resolveSchemeBaseRgb(): ModelRGB
    {
        return match ($this->color->type()) {
            'hex', 'rgb' => $this->perceptionRgbFromInput($this->color),
            'cmyk' => new ModelRGB($this->cmyk2rgbProfiled($this->color)->toArray()),
            default => throw new \InvalidArgumentException('Color type "' . $this->color->type() . '" is unknown'),
        };
    }

    /**
     * Converts RGB or HEX input to profiled perception RGB.
     */
    private function perceptionRgbFromInput(ColorModel $color): ModelRGB
    {
        $rgb = $color instanceof RGB
            ? $color
            : new RGB($color->toRGB()->toString());

        $cmykProfiled = $this->rgb2cmykProfiled($rgb);
        $perceptionRgb = $this->cmyk2rgbProfiled($cmykProfiled);

        return new ModelRGB($perceptionRgb->toArray());
    }

    /**
     * Builds the complete scheme map in the established output order.
     *
     * @return SchemeMap
     */
    private function buildSchemes(ModelRGB $schemeBaseRgb, bool $profiled = false): array
    {
        $schemes = [];

        foreach (self::SCHEME_ORDER as $name) {
            if (in_array($name, self::CONTINUOUS_SCHEMES, true)) {
                $colors = $this->buildContinuousSchemeColors($schemeBaseRgb, $name);
                $schemes[$name] = $this->convertSchemeColors($colors, $profiled, false);
                continue;
            }

            $schemeClass = self::DISCRETE_SCHEME_CLASSES[$name];
            $schemes[$name] = $this->convertSchemeColors(
                (new $schemeClass($schemeBaseRgb, self::SCHEME_STEPS))->get(),
                $profiled,
                true,
            );
        }

        return $schemes;
    }

    /**
     * Builds generated RGB colors for continuous schemes.
     *
     * @return array<int, ModelRGB>
     */
    private function buildContinuousSchemeColors(ModelRGB $base, string $name): array
    {
        $black = new ModelRGB(0, 0, 0);
        $white = new ModelRGB(255, 255, 255);

        return match ($name) {
            'hue'   => $this->buildHueColors($base, self::SCHEME_STEPS),
            'tint'  => $this->mixToward($base, $white, self::SCHEME_STEPS, 0.9),
            'shade' => $this->mixToward($base, $black, self::SCHEME_STEPS, 1.0),
            'tone'  => $this->mixToward($base, $black, self::SCHEME_STEPS, 0.55),
            default => throw new \InvalidArgumentException('Unknown continuous scheme "' . $name . '"'),
        };
    }

    /**
     * Rotates hue across the full color wheel.
     *
     * @return array<int, ModelRGB>
     */
    private function buildHueColors(ModelRGB $base, int $steps): array
    {
        $stepDegrees = 360 / ($steps - 1);
        $colors = [];

        for ($i = 0; $i < $steps; $i++) {
            $colors[] = $base->hue($i * $stepDegrees);
        }

        return $colors;
    }

    /**
     * Mixes a base color toward a target color over the requested number of steps.
     *
     * @return array<int, ModelRGB>
     */
    private function mixToward(
        ModelRGB $base,
        ModelRGB $target,
        int $steps,
        float $maxWeight,
    ): array {
        $colors = [];

        for ($i = 0; $i < $steps; $i++) {
            $weight = $steps > 1 ? ($i / ($steps - 1)) * $maxWeight : 0;
            $colors[] = $base->mix($target, $weight);
        }

        return $colors;
    }

    /**
     * Normalizes color objects to RGB models while preserving source keys.
     *
     * @param array<int|string, mixed> $colors
     * @return RgbMap
     */
    private function normalizeRgbColors(array $colors): array
    {
        $normalized = [];

        foreach ($colors as $key => $color) {
            if (! is_object($color)) {
                continue;
            }

            $normalized[$key] = $color instanceof ModelRGB
                ? $color
                : $color->toRGB();
        }

        return $normalized;
    }

    /**
     * Converts raw scheme colors to formatted swatches.
     *
     * Discrete schemes use a CMYK-to-RGB round trip for profiled perception. Continuous schemes keep
     * their mathematically generated RGB progression and only profile CMYK values.
     *
     * @param array<int|string, mixed> $colors
     * @return array<int|string, SchemeSwatch>
     */
    private function convertSchemeColors(array $colors, bool $profiled = false, bool $profileRgb = true): array
    {
        $normalized = $this->normalizeRgbColors($colors);

        if ($profiled && $normalized !== []) {
            return $this->formatProfiledSchemeColors($normalized, $profileRgb);
        }

        return $this->formatUnprofiledSchemeColors($normalized);
    }

    /**
     * Formats RGB colors with mathematical CMYK fallback values.
     *
     * @param RgbMap $colors
     * @return array<int|string, SchemeSwatch>
     */
    private function formatUnprofiledSchemeColors(array $colors): array
    {
        $result = [];

        foreach ($colors as $key => $color) {
            $result[$key] = $this->formatSchemeColorFromRgb($color);
        }

        return $result;
    }

    /**
     * Formats RGB colors with profiled CMYK values and optional profiled RGB perception.
     *
     * @param RgbMap $colors
     * @return array<int|string, SchemeSwatch>
     */
    private function formatProfiledSchemeColors(array $colors, bool $profileRgb): array
    {
        $cmyks = $this->iccColorConverter->rgbToCmykBatch($this->toServiceRgbs($colors));
        $rgbs = $profileRgb ? $this->iccColorConverter->cmykToRgbBatch($cmyks) : [];
        $result = [];
        $index = 0;

        foreach ($colors as $key => $color) {
            $cmyk = $cmyks[$index] ?? null;
            $rgb = $rgbs[$index] ?? null;
            $index++;

            $result[$key] = $this->formatSchemeColorFromRgb(
                new ModelRGB(($rgb ?? new RGB($color->toArray()))->toArray()),
                $cmyk,
            );
        }

        return $result;
    }

    /**
     * Converts generic RGB models into service RGB models for ICC batch conversion.
     *
     * @param RgbMap $colors
     * @return array<int, RGB>
     */
    private function toServiceRgbs(array $colors): array
    {
        return array_map(
            static fn (ModelRGB $color): RGB => new RGB($color->toArray()),
            array_values($colors),
        );
    }

    /**
     * Formats one RGB color into the public scheme swatch shape.
     *
     * @return SchemeSwatch
     */
    private function formatSchemeColorFromRgb(
        ModelRGB $rgb,
        ?CMYK $profiledCmyk = null,
    ): array {
        $displayRgb = new RGB($rgb->toArray());
        $cmyk = $profiledCmyk ?? $this->mathCmykFromRgb($rgb);

        return [
            'cmyk'     => $cmyk->toString(),
            'hex'      => $displayRgb->toHex()->toString(),
            'rgb'      => $displayRgb->toString(),
            'rgb_math' => $rgb->toString(),
        ];
    }

    /**
     * Converts an RGB model to integer CMYK percentages without ICC profiles.
     */
    private function mathCmykFromRgb(ModelRGB $rgb): CMYK
    {
        $cmykMath = $rgb->toCmyk();

        return new CMYK(
            (int) round($cmykMath->cyan * 100),
            (int) round($cmykMath->magenta * 100),
            (int) round($cmykMath->yellow * 100),
            (int) round($cmykMath->key * 100),
        );
    }

    /**
     * Converts CMYK to RGB through the configured ICC converter.
     */
    private function cmyk2rgbProfiled(CMYK $cmyk): RGB
    {
        return $this->iccColorConverter->cmykToRgb($cmyk);
    }

    /**
     * Converts RGB to CMYK through the configured ICC converter.
     */
    private function rgb2cmykProfiled(RGB $rgb): CMYK
    {
        return $this->iccColorConverter->rgbToCmyk($rgb);
    }
}
