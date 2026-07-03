<?php

declare (strict_types = 1);

namespace wwaz\ColorService\Processing;

use wwaz\Colormodel\Model\Hex as HEX;
use wwaz\Colormodel\Model\HSL;

class ColorMetrics
{
    /**
     * Checks whether a HEX color should be considered light.
     *
     * @param string $hex
     * @return bool
     */
    public function isLight(string $hex): bool
    {
        return $this->getRelativeLuminance($hex) >= 0.5;
    }

    /**
     * Checks whether a HEX color should be considered dark.
     *
     * @param string $hex
     * @return bool
     */
    public function isDark(string $hex): bool
    {
        return ! $this->isLight($hex);
    }

    /**
     * Calculates the HSL lightness of a HEX color.
     *
     * @param string $hex HEX color (#RRGGBB, RRGGBB, #RGB, or RGB)
     * @return float Lightness percentage (0 - 100)
     */
    public function getLightness(string $hex): float
    {
        return round((new HEX($hex))->toHSL()->lightness, 2);
    }

    /**
     * Rates the WCAG contrast between two HEX colors.
     *
     * @param string $hex1 First color (#RRGGBB or #RGB)
     * @param string $hex2 Second color (#RRGGBB or #RGB)
     * @return string A | AA | AAA
     */
    public function getContrastRating(string $hex1, string $hex2): string
    {
        $ratio = $this->getContrastRatio($hex1, $hex2);

        return match (true) {
            $ratio >= 7.0 => 'AAA',
            $ratio >= 4.5 => 'AA',
            default       => 'A',
        };
    }

    /**
     * Finds the readable black or white text color for a background.
     *
     * @param string $backgroundHex Background color
     * @return string '#000000' or '#FFFFFF'
     */
    public function getReadableTextColor(string $backgroundHex): string
    {
        $blackContrast = $this->getContrastRatio($backgroundHex, '#000000');
        $whiteContrast = $this->getContrastRatio($backgroundHex, '#FFFFFF');

        return ($blackContrast >= $whiteContrast)
            ? '#000000'
            : '#FFFFFF';
    }

    /**
     * Finds a readable matching text color while keeping the HSL hue.
     *
     * Default: minimum contrast 2.1:1
     *
     * @param string $backgroundHex Background color
     * @param float  $minContrast Minimum contrast ratio
     * @return string HEX color
     */
    public function getReadableTextColorKeepingHue(string $backgroundHex, float $minContrast = 2.1): string
    {
        $hsl = (new HEX($backgroundHex))->toHSL();

        $direction = $this->isLight($backgroundHex) ? -1 : 1;

        $bestColor    = $backgroundHex;
        $bestContrast = 1.0;

        for ($step = 1; $step <= 100; $step++) {

            $newLightness = max(0, min(100, $hsl->lightness + ($step * $direction)));
            $candidate    = '#' . (string) (new HSL($hsl->hue, $hsl->saturation, $newLightness))->toHex();

            $contrast = $this->getContrastRatio($backgroundHex, $candidate);
            if ($contrast > $bestContrast) {
                $bestContrast = $contrast;
                $bestColor    = $candidate;
            }

            if ($contrast >= $minContrast) {
                return $candidate;
            }
        }

        return $bestColor;
    }

    /**
     * Calculates the WCAG contrast ratio between two HEX colors.
     *
     * @param string $hex1 First color (#RRGGBB or #RGB)
     * @param string $hex2 Second color (#RRGGBB or #RGB)
     * @return float Contrast ratio (1.0 to 21.0)
     */
    public function getContrastRatio(string $hex1, string $hex2): float
    {
        $l1 = $this->getRelativeLuminance($hex1);
        $l2 = $this->getRelativeLuminance($hex2);

        $lighter = max($l1, $l2);
        $darker  = min($l1, $l2);

        return round(($lighter + 0.05) / ($darker + 0.05), 2);
    }

    /**
     * Calculates a color's relative luminance according to WCAG.
     */
    public function getRelativeLuminance(string $hex): float
    {
        $rgb = (new HEX($hex))->toRGB();

        $r = $this->linearizeRgbChannel($rgb->getRed());
        $g = $this->linearizeRgbChannel($rgb->getGreen());
        $b = $this->linearizeRgbChannel($rgb->getBlue());

        return 0.2126 * $r + 0.7152 * $g + 0.0722 * $b;
    }

    private function linearizeRgbChannel(int $channel): float
    {
        $value = $channel / 255;

        return ($value <= 0.03928)
            ? $value / 12.92
            : pow(($value + 0.055) / 1.055, 2.4);
    }
}
