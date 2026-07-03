<?php

declare(strict_types=1);

namespace wwaz\ColorService\Tests;

use PHPUnit\Framework\TestCase;
use wwaz\ColorService\Color\CMYK;
use wwaz\ColorService\Color\HEX;
use wwaz\ColorService\Color\RGB;

class ColorModelTest extends TestCase
{
    public function test_hex_adds_missing_hash_and_reports_rgb_color_space(): void
    {
        $hex = new HEX('ff00ff');

        $this->assertSame('hex', $hex->type());
        $this->assertSame('rgb', $hex->colorSpace());
        $this->assertSame('#ff00ff', $hex->toString());
        $this->assertSame('#ff00ff', $hex->toColorString());
    }

    public function test_hex_keeps_existing_hash(): void
    {
        $this->assertSame('#fff', (new HEX('#fff'))->toString());
    }

    public function test_rgb_reports_color_space_and_percentage_string(): void
    {
        $rgb = new RGB('255,128,0');

        $this->assertSame('rgb', $rgb->type());
        $this->assertSame('rgb', $rgb->colorSpace());
        $this->assertSame('rgb(255%,128%,0%)', $rgb->toColorStringPercentage());
    }

    public function test_cmyk_reports_color_space_and_supports_short_property_aliases(): void
    {
        $cmyk = new CMYK('10,20,30,40');

        $this->assertSame('cmyk', $cmyk->type());
        $this->assertSame('cmyk', $cmyk->colorSpace());
        $this->assertSame(10, $cmyk->c);
        $this->assertSame(20, $cmyk->m);
        $this->assertSame(30, $cmyk->y);
        $this->assertSame(40, $cmyk->k);
        $this->assertNull($cmyk->unknown);
    }

    public function test_cmyk_percentage_string_uses_all_channels(): void
    {
        $this->assertSame('cmyk(10%,20%,30%,40%)', (new CMYK('10,20,30,40'))->toColorStringPercentage());
    }
}
