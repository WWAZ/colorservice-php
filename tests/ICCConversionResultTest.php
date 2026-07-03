<?php

declare(strict_types=1);

namespace wwaz\ColorService\Tests;

use PHPUnit\Framework\TestCase;
use wwaz\ColorService\DTO\ICCConversionResultV1;
use wwaz\ColorService\DTO\ICCConversionResultV2;

class ICCConversionResultTest extends TestCase
{
    public function test_v1_accessors_read_legacy_payload(): void
    {
        $data = [
            'colorWorkSpace' => ['rgb' => 'rgb-profile', 'cmyk' => 'cmyk-profile'],
            'intent' => 'relative',
            'given' => [
                'colorSpace' => 'rgb',
                'representation' => 'hex',
                'value' => '#ff00ff',
            ],
            'values' => [
                'rgb' => '255,0,255',
                'hex' => '#ff00ff',
                'cmyk' => '0,100,0,0',
            ],
            'perception' => [
                'rgb' => '250,0,250',
                'hex' => '#fa00fa',
            ],
            'profiled' => [],
            'inField' => [],
            'name' => 'Magenta',
            'conversionStack' => ['init | 0'],
        ];

        $result = new ICCConversionResultV1($data);

        $this->assertSame($data, $result->toArray());
        $this->assertSame('Magenta', $result->name());
        $this->assertSame('#fa00fa', $result->hexPerception());
        $this->assertSame('250,0,250', $result->rgbPerception());
        $this->assertSame('255,0,255', $result->rgb());
        $this->assertSame('#ff00ff', $result->hex());
        $this->assertSame('0,100,0,0', $result->cmyk());
        $this->assertSame('rgb', $result->givenColorspace());
        $this->assertSame('hex', $result->givenType());
        $this->assertSame('#ff00ff', $result->givenValue());
    }

    public function test_v2_accessors_read_slim_payload(): void
    {
        $data = [
            'given' => [
                'colorSpace' => 'rgb',
                'representation' => 'rgb',
                'value' => '255,0,0',
            ],
            'hex' => '#ff0000',
            'rgb' => '255,0,0',
            'cmyk' => '0,100,100,0',
            'perception' => '#ee0000',
            'name' => 'Red',
        ];

        $result = new ICCConversionResultV2($data);

        $this->assertSame($data, $result->toArray());
        $this->assertSame('Red', $result->name());
        $this->assertSame('#ee0000', $result->perception());
        $this->assertSame('255,0,0', $result->rgb());
        $this->assertSame('#ff0000', $result->hex());
        $this->assertSame('0,100,100,0', $result->cmyk());
    }
}
