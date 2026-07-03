<?php

declare(strict_types=1);

namespace wwaz\ColorService\Tests;

use PHPUnit\Framework\TestCase;
use wwaz\ColorService\Color\CMYK;
use wwaz\ColorService\Color\RGB;
use wwaz\ColorService\Processing\IccColorConverter;

class IccColorConverterTest extends TestCase
{
    private const RGB_PROFILE = 'sRGB_v4_ICC_preference';

    private const CMYK_PROFILE = 'ISOcoated_v2_300_eci';

    public function test_math_fallback_rgb_to_cmyk(): void
    {
        $converter = new IccColorConverter();
        $cmyk = $converter->rgbToCmyk(new RGB('255,0,0'));

        $this->assertSame('cmyk', $cmyk->type());
        $this->assertNotEmpty($cmyk->toString());
    }

    public function test_math_fallback_cmyk_to_rgb(): void
    {
        $converter = new IccColorConverter();
        $rgb = $converter->cmykToRgb(new CMYK('0,100,100,0'));

        $this->assertSame('rgb', $rgb->type());
        $this->assertNotEmpty($rgb->toString());
    }

    public function test_default_intent_is_relative(): void
    {
        $this->assertSame('relative', (new IccColorConverter())->getIntent());
    }

    public function test_unknown_intent_falls_back_to_relative(): void
    {
        $converter = new IccColorConverter();
        $converter->setIntent('nonsense');

        $this->assertSame('relative', $converter->getIntent());
    }

    public function test_valid_intent_is_normalized(): void
    {
        $converter = new IccColorConverter();
        $converter->setIntent('PERCEPTUAL');

        $this->assertSame('perceptual', $converter->getIntent());
    }

    public function test_unknown_profile_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new IccColorConverter())->setProfile('rgb', 'this-profile-does-not-exist');
    }

    public function test_sentinel_profile_values_clear_profile(): void
    {
        $converter = new IccColorConverter();

        $this->assertTrue($converter->setProfile('rgb', 'none'));
        $this->assertNull($converter->getProfile('rgb'));

        $this->assertTrue($converter->setProfile('cmyk', 'undefined'));
        $this->assertNull($converter->getProfile('cmyk'));
    }

    public function test_profiled_rgb_to_cmyk(): void
    {
        $converter = new IccColorConverter();
        $converter->setProfile('rgb', self::RGB_PROFILE);
        $converter->setProfile('cmyk', self::CMYK_PROFILE);

        $cmyk = $converter->rgbToCmyk(new RGB('255,0,0'));

        $this->assertSame('cmyk', $cmyk->type());
        $this->assertMatchesRegularExpression('/^\d+,/', $cmyk->toString());
    }

    public function test_profiled_cmyk_to_rgb(): void
    {
        $converter = new IccColorConverter();
        $converter->setProfile('rgb', self::RGB_PROFILE);
        $converter->setProfile('cmyk', self::CMYK_PROFILE);

        $rgb = $converter->cmykToRgb(new CMYK('0,100,100,0'));

        $this->assertSame('rgb', $rgb->type());
        $this->assertMatchesRegularExpression('/^\d+,/', $rgb->toString());
    }

    public function test_profiled_cmyk_to_rgb_batch(): void
    {
        $converter = new IccColorConverter();
        $converter->setProfile('rgb', self::RGB_PROFILE);
        $converter->setProfile('cmyk', self::CMYK_PROFILE);

        $results = $converter->cmykToRgbBatch([
            new CMYK('53,0,60,29'),
            new CMYK('0,100,100,0'),
        ]);

        $this->assertCount(2, $results);
        $this->assertSame('rgb', $results[0]->type());
        $this->assertSame('rgb', $results[1]->type());
    }

    public function test_batch_helpers_return_empty_array_for_empty_input(): void
    {
        $converter = new IccColorConverter();

        $this->assertSame([], $converter->rgbToCmykBatch([]));
        $this->assertSame([], $converter->cmykToRgbBatch([]));
    }
}
