<?php

declare(strict_types=1);

namespace wwaz\ColorService\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use wwaz\ColorService\Processing\ColorMetrics;

class ColorMetricsTest extends TestCase
{
    public function test_light_and_dark_classification_uses_relative_luminance(): void
    {
        $metrics = new ColorMetrics();

        $this->assertTrue($metrics->isLight('#ffffff'));
        $this->assertFalse($metrics->isDark('#ffffff'));
        $this->assertTrue($metrics->isDark('#000000'));
        $this->assertFalse($metrics->isLight('#000000'));
    }

    public function test_lightness_is_returned_as_hsl_percentage(): void
    {
        $metrics = new ColorMetrics();

        $this->assertSame(50.0, $metrics->getLightness('#ff0000'));
        $this->assertSame(100.0, $metrics->getLightness('#ffffff'));
        $this->assertSame(0.0, $metrics->getLightness('#000000'));
    }

    #[DataProvider('contrastRatingProvider')]
    public function test_contrast_rating_thresholds(string $foreground, string $background, string $expected): void
    {
        $this->assertSame($expected, (new ColorMetrics())->getContrastRating($foreground, $background));
    }

    /**
     * @return list<array{string, string, string}>
     */
    public static function contrastRatingProvider(): array
    {
        return [
            ['#000000', '#ffffff', 'AAA'],
            ['#767676', '#ffffff', 'AA'],
            ['#aaaaaa', '#ffffff', 'A'],
        ];
    }

    public function test_contrast_ratio_is_symmetric_and_rounded(): void
    {
        $metrics = new ColorMetrics();

        $this->assertSame(21.0, $metrics->getContrastRatio('#000000', '#ffffff'));
        $this->assertSame(
            $metrics->getContrastRatio('#123456', '#abcdef'),
            $metrics->getContrastRatio('#abcdef', '#123456'),
        );
    }

    public function test_readable_text_color_chooses_higher_contrast_between_black_and_white(): void
    {
        $metrics = new ColorMetrics();

        $this->assertSame('#000000', $metrics->getReadableTextColor('#ffffff'));
        $this->assertSame('#FFFFFF', $metrics->getReadableTextColor('#000000'));
    }

    public function test_readable_text_color_keeping_hue_adjusts_lightness_until_contrast_is_met(): void
    {
        $metrics = new ColorMetrics();

        $textColor = $metrics->getReadableTextColorKeepingHue('#333333', 2.1);

        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $textColor);
        $this->assertGreaterThanOrEqual(2.1, $metrics->getContrastRatio('#333333', $textColor));
    }

    public function test_relative_luminance_supports_short_hex_notation(): void
    {
        $metrics = new ColorMetrics();

        $this->assertSame(
            $metrics->getRelativeLuminance('#ffffff'),
            $metrics->getRelativeLuminance('#fff'),
        );
    }
}
