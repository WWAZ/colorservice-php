<?php

declare(strict_types=1);

namespace wwaz\ColorService\Processing;

use wwaz\Colorconvert\Convert as ColorConvertEngine;
use wwaz\Colorprofile\Facades\Finder as ColorprofileFinder;
use wwaz\ColorService\Color\CMYK;
use wwaz\ColorService\Color\RGB;

class IccColorConverter
{
    public const COLORSPACE_RGB = 'rgb';

    public const COLORSPACE_CMYK = 'cmyk';

    private const EMPTY_PROFILE_VALUES = ['undefined', 'none'];

    private const VALID_INTENTS = ['perceptual', 'relative', 'saturation', 'absolute'];

    /** @var array{rgb: ?object, cmyk: ?object} */
    protected array $profiles = [
        self::COLORSPACE_RGB => null,
        self::COLORSPACE_CMYK => null,
    ];

    protected ?string $appliedRgbProfile = null;

    protected ?string $appliedCmykProfile = null;

    protected ?string $intent = null;

    protected ?string $appliedIntent = null;

    /**
     * Assigns an ICC profile for the given color space.
     *
     * Passing null, an empty string, "undefined", or "none" clears the profile.
     *
     * @param self::COLORSPACE_RGB|self::COLORSPACE_CMYK $colorSpace
     * @throws \InvalidArgumentException When the color space or profile name is unknown.
     */
    public function setProfile(string $colorSpace, object|string|null $objOrFilename): bool
    {
        $this->assertSupportedColorSpace($colorSpace);

        if ($this->isEmptyProfileValue($objOrFilename)) {
            $this->profiles[$colorSpace] = null;

            return true;
        }

        if (is_object($objOrFilename)) {
            $this->profiles[$colorSpace] = $objOrFilename;

            return true;
        }

        if ($profile = ColorprofileFinder::find($objOrFilename)) {
            $this->profiles[$colorSpace] = $profile;

            return true;
        }

        throw new \InvalidArgumentException('Colorprofile ' . $objOrFilename . ' is unknown');
    }

    /**
     * Returns the active ICC profile for a color space, if one is configured.
     *
     * @param self::COLORSPACE_RGB|self::COLORSPACE_CMYK $colorSpace
     */
    public function getProfile(string $colorSpace): ?object
    {
        $this->assertSupportedColorSpace($colorSpace);

        return $this->profiles[$colorSpace] ?: null;
    }

    /**
     * Sets the rendering intent used by the underlying color conversion engine.
     */
    public function setIntent(?string $intent): self
    {
        $this->intent = $intent;

        return $this;
    }

    /**
     * Returns a normalized rendering intent supported by the conversion engine.
     */
    public function getIntent(): string
    {
        if ($this->intent === null || $this->intent === '') {
            return 'relative';
        }

        $intent = strtolower($this->intent);

        return in_array($intent, self::VALID_INTENTS, true) ? $intent : 'relative';
    }

    /**
     * Converts a CMYK value to RGB, using ICC profiles only when both profiles are set.
     */
    public function cmykToRgb(CMYK $cmyk): RGB
    {
        if (! $this->hasProfile(self::COLORSPACE_CMYK) || ! $this->hasProfile(self::COLORSPACE_RGB)) {
            return $this->cmykToRgbMath($cmyk);
        }

        $this->applyIccProfiles();

        return $this->cmykToRgbProfiled($cmyk);
    }

    /**
     * Converts multiple CMYK values to RGB with one shared ICC profile setup.
     *
     * @param array<int, CMYK> $cmyks
     * @return array<int, RGB>
     */
    public function cmykToRgbBatch(array $cmyks): array
    {
        if ($cmyks === []) {
            return [];
        }

        if (! $this->hasProfile(self::COLORSPACE_CMYK) || ! $this->hasProfile(self::COLORSPACE_RGB)) {
            return array_map(
                fn (CMYK $cmyk): RGB => $this->cmykToRgbMath($cmyk),
                $cmyks,
            );
        }

        $this->applyIccProfiles();

        return array_map(
            fn (CMYK $cmyk): RGB => $this->cmykToRgbProfiled($cmyk),
            $cmyks,
        );
    }

    /**
     * Converts an RGB value to CMYK, using ICC profiles when a CMYK target is set.
     */
    public function rgbToCmyk(RGB $rgb): CMYK
    {
        if (! $this->hasProfile(self::COLORSPACE_CMYK)) {
            return $this->rgbToCmykMath($rgb);
        }

        $this->applyIccProfiles();

        return $this->rgbToCmykProfiled($rgb);
    }

    /**
     * Converts multiple RGB values to CMYK with one shared ICC profile setup.
     *
     * @param array<int, RGB> $rgbs
     * @return array<int, CMYK>
     */
    public function rgbToCmykBatch(array $rgbs): array
    {
        if ($rgbs === []) {
            return [];
        }

        if (! $this->hasProfile(self::COLORSPACE_CMYK)) {
            return array_map(
                fn (RGB $rgb): CMYK => $this->rgbToCmykMath($rgb),
                $rgbs,
            );
        }

        $this->applyIccProfiles();

        return array_map(
            fn (RGB $rgb): CMYK => $this->rgbToCmykProfiled($rgb),
            $rgbs,
        );
    }

    /**
     * Converts CMYK to RGB with the mathematical fallback from the color model.
     */
    protected function cmykToRgbMath(CMYK $cmyk): RGB
    {
        return new RGB($cmyk->toRgb()->toArray());
    }

    /**
     * Converts CMYK to RGB through the configured ICC conversion engine.
     */
    protected function cmykToRgbProfiled(CMYK $cmyk): RGB
    {
        return new RGB(ColorConvertEngine::toRGB($this->toCmykIntArray($cmyk))->toArray());
    }

    /**
     * Converts RGB to integer CMYK percentages with the color model fallback.
     */
    protected function rgbToCmykMath(RGB $rgb): CMYK
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
     * Converts RGB to CMYK through the configured ICC conversion engine.
     */
    protected function rgbToCmykProfiled(RGB $rgb): CMYK
    {
        return new CMYK($this->toCmykIntArray(ColorConvertEngine::toCMYK($rgb)));
    }

    /**
     * Normalizes CMYK values from ratio or percent based engines to integer percentages.
     *
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    protected function toCmykIntArray(object $cmyk): array
    {
        $values = $cmyk->toArray();
        $scale = max(array_map('abs', $values)) <= 1 ? 100 : 1;

        return [
            (int) round((float) $values[0] * $scale),
            (int) round((float) $values[1] * $scale),
            (int) round((float) $values[2] * $scale),
            (int) round((float) $values[3] * $scale),
        ];
    }

    /**
     * Applies profiles only when the requested profile/intent combination changed.
     */
    protected function applyIccProfiles(): void
    {
        $rgbName = $this->getProfile(self::COLORSPACE_RGB)?->name();
        $cmykName = $this->getProfile(self::COLORSPACE_CMYK)?->name();
        $intent = $this->getIntent();

        if (
            $rgbName === $this->appliedRgbProfile
            && $cmykName === $this->appliedCmykProfile
            && $intent === $this->appliedIntent
        ) {
            return;
        }

        ColorConvertEngine::setCMYKProfile($cmykName);
        ColorConvertEngine::setRGBProfile($rgbName);
        ColorConvertEngine::setIntent($intent);

        $this->appliedRgbProfile = $rgbName;
        $this->appliedCmykProfile = $cmykName;
        $this->appliedIntent = $intent;
    }

    /**
     * Checks whether a profile is configured for the given color space.
     *
     * @param self::COLORSPACE_RGB|self::COLORSPACE_CMYK $colorSpace
     */
    protected function hasProfile(string $colorSpace): bool
    {
        return $this->profiles[$colorSpace] !== null;
    }

    /**
     * Detects profile values that should clear the active profile.
     */
    protected function isEmptyProfileValue(object|string|null $profile): bool
    {
        return $profile === null
            || (is_string($profile) && (
                trim($profile) === ''
                || in_array(strtolower($profile), self::EMPTY_PROFILE_VALUES, true)
            ));
    }

    /**
     * Ensures only supported color spaces can access the profile map.
     *
     * @param self::COLORSPACE_RGB|self::COLORSPACE_CMYK|string $colorSpace
     * @throws \InvalidArgumentException
     */
    protected function assertSupportedColorSpace(string $colorSpace): void
    {
        if (! array_key_exists($colorSpace, $this->profiles)) {
            throw new \InvalidArgumentException('Colorspace ' . $colorSpace . ' is unknown');
        }
    }
}
