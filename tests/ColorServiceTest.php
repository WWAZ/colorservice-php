<?php

declare(strict_types=1);

namespace wwaz\ColorService\Tests;

use PHPUnit\Framework\TestCase;
use wwaz\ColorService\Color\CMYK;
use wwaz\ColorService\ColorService;
use wwaz\ColorService\DTO\ICCConversionResultV1;
use wwaz\ColorService\DTO\ICCConversionResultV2;

class ColorServiceTest extends TestCase
{
    private const RGB_PROFILE = 'sRGB_v4_ICC_preference';

    private const CMYK_PROFILE = 'ISOcoated_v2_300_eci';

    public function test_constructor_stores_profiles_and_default_intent(): void
    {
        $service = $this->createService();

        $this->assertSame(self::RGB_PROFILE, $service->rgbProfile);
        $this->assertSame(self::CMYK_PROFILE, $service->cmykProfile);
        $this->assertSame('relative', $service->intent);
    }

    public function test_icc_convert_accepts_rgb_string_and_returns_v2_by_default(): void
    {
        $result = $this->createService()->ICCConvert('255,0,0');

        $this->assertInstanceOf(ICCConversionResultV2::class, $result);
        $this->assertSame('255,0,0', $result->rgb());
    }

    public function test_icc_convert_can_return_legacy_v1_result(): void
    {
        $service = $this->createService(acceptICCConversionDTO: 'v1');

        $result = $service->ICCConvert('255,0,0');

        $this->assertInstanceOf(ICCConversionResultV1::class, $result);
        $this->assertSame('rgb', $result->givenType());
        $this->assertSame('255,0,0', $result->givenValue());
    }

    public function test_icc_convert_accepts_color_objects(): void
    {
        $result = $this->createService()->ICCConvert(new CMYK('0,100,100,0'));

        $this->assertSame('cmyk', $result->toArray()['given']['representation']);
        $this->assertMatchesRegularExpression('/^\d+,/', $result->rgb());
    }

    public function test_icc_convert_batch_preserves_input_order(): void
    {
        $results = $this->createService()->ICCConvertBatch([
            '255,0,0',
            '00ff00',
        ]);

        $this->assertCount(2, $results);
        $this->assertSame('255,0,0', $results[0]->toArray()['given']['value']);
        $this->assertSame('#00ff00', $results[1]->toArray()['given']['value']);
    }

    public function test_name_and_lightness_helpers_use_conversion_perception(): void
    {
        $service = $this->createService();

        $this->assertNotSame('', $service->name('255,0,0'));
        $this->assertTrue($service->isLight('255,255,255'));
        $this->assertTrue($service->isDark('0,0,0'));
    }

    public function test_text_color_helpers_return_hex_colors(): void
    {
        $service = $this->createService();

        $this->assertSame('#000000', $service->getReadableTextColor('255,255,255'));
        $this->assertMatchesRegularExpression('/^#[0-9A-Fa-f]{6}$/', $service->getReadableTextColorKeepingHue('0,0,0'));
    }

    public function test_schema_returns_named_scheme_and_rejects_unknown_name(): void
    {
        $service = $this->createService();

        $complementary = $service->schema('255,0,0', 'complementary');

        $this->assertNotEmpty($complementary);
        $this->assertArrayHasKey('hex', $complementary[0]);

        $this->expectException(\InvalidArgumentException::class);

        $service->schema('255,0,0', 'not-a-schema');
    }

    public function test_unknown_color_object_type_throws(): void
    {
        $service = $this->createService();

        $this->expectException(\InvalidArgumentException::class);

        $service->ICCConvert(new class {
            public function type(): string
            {
                return 'rgba';
            }

            public function toString(): string
            {
                return '0,0,0,1';
            }
        });
    }

    private function createService(
        ?string $intent = null,
        ?string $acceptICCConversionDTO = null,
    ): ColorService {
        return new ColorService(
            self::RGB_PROFILE,
            self::CMYK_PROFILE,
            intent: $intent,
            acceptICCConversionDTO: $acceptICCConversionDTO,
        );
    }
}
