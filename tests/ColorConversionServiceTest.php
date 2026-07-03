<?php

declare(strict_types=1);

namespace wwaz\ColorService\Tests;

use PHPUnit\Framework\TestCase;
use wwaz\ColorService\Color\CMYK;
use wwaz\ColorService\Color\HEX;
use wwaz\ColorService\Color\RGB;
use wwaz\ColorService\DTO\ICCConversionResultV1;
use wwaz\ColorService\DTO\ICCConversionResultV2;
use wwaz\ColorService\Processing\ColorConversionService;

class ColorConversionServiceTest extends TestCase
{
    private const RGB_PROFILE = 'sRGB_v4_ICC_preference';

    private const CMYK_PROFILE = 'ISOcoated_v2_300_eci';

    public function test_hex_input_keeps_given_representation_in_v1_result(): void
    {
        $service = new ColorConversionService(new HEX('f0f'));
        $service->setAcceptICCConversionDTO('v1');
        $service->setProfile('rgb', self::RGB_PROFILE);
        $service->setProfile('cmyk', self::CMYK_PROFILE);

        $result = $service->convert();
        $given = $result->toArray()['given'];

        $this->assertInstanceOf(ICCConversionResultV1::class, $result);
        $this->assertSame('hex', $given['representation']);
        $this->assertSame('#f0f', $given['value']);
        $this->assertSame('rgb', $given['colorSpace']);
    }

    public function test_convert_returns_v2_dto_by_default(): void
    {
        $service = new ColorConversionService(new RGB('255,0,0'));

        $result = $service->convert();
        $data = $result->toArray();

        $this->assertInstanceOf(ICCConversionResultV2::class, $result);
        $this->assertSame('255,0,0', $data['rgb']);
        $this->assertArrayHasKey('hex', $data);
        $this->assertArrayHasKey('cmyk', $data);
        $this->assertArrayHasKey('perception', $data);
        $this->assertArrayNotHasKey('schemes', $data);
    }

    public function test_v1_result_includes_profiles_intent_and_conversion_stack(): void
    {
        $service = new ColorConversionService(new RGB('255,0,0'));
        $service->setAcceptICCConversionDTO('v1');
        $service->setIntent('perceptual');
        $service->setProfile('rgb', self::RGB_PROFILE);
        $service->setProfile('cmyk', self::CMYK_PROFILE);

        $data = $service->convert()->toArray();

        $this->assertSame('perceptual', $data['intent']);
        $this->assertSame(self::RGB_PROFILE, $data['colorWorkSpace']['rgb']);
        $this->assertSame(self::CMYK_PROFILE, $data['colorWorkSpace']['cmyk']);
        $this->assertContains('init', $this->stackStepNames($data['conversionStack']));
        $this->assertContains('convert_start', $this->stackStepNames($data['conversionStack']));
        $this->assertContains('rgb2cmyk', $this->stackStepNames($data['conversionStack']));
    }

    public function test_with_include_schemes_adds_schemes_to_v2_result(): void
    {
        $service = new ColorConversionService(new RGB('255,0,0'));
        $service->setProfile('rgb', self::RGB_PROFILE);
        $service->setProfile('cmyk', self::CMYK_PROFILE);

        $data = $service->withIncludeSchemes()->convert()->toArray();

        $this->assertArrayHasKey('schemes', $data);
        $this->assertArrayHasKey('complementary', $data['schemes']);
        $this->assertNotEmpty($data['schemes']['complementary']);
        $this->assertArrayHasKey('hex', $data['schemes']['complementary'][0]);
    }

    public function test_schemes_can_use_profiled_perception_as_base(): void
    {
        $service = new ColorConversionService(new RGB('0,158,227'));
        $service->setProfile('rgb', self::RGB_PROFILE);
        $service->setProfile('cmyk', self::CMYK_PROFILE);

        $conversion = $service->convert()->toArray();
        $schemes = $service->schemes(true);

        $this->assertSame(
            $this->normalizeHex($conversion['perception']),
            $this->normalizeHex($schemes['complementary'][0]['hex']),
        );
    }

    public function test_cmyk_input_schemes_keep_given_cmyk_for_base_swatch(): void
    {
        $service = new ColorConversionService(new CMYK('100,0,0,0'));
        $service->setProfile('rgb', self::RGB_PROFILE);
        $service->setProfile('cmyk', self::CMYK_PROFILE);
        $service->convert();

        $schemes = $service->schemes(true);
        $baseCmyk = array_map(
            static fn (string $value): int => (int) round((float) $value),
            explode(',', $schemes['complementary'][0]['cmyk']),
        );

        $this->assertSame([100, 0, 0, 0], $baseCmyk);
    }

    public function test_conversion_stack_is_publicly_readable(): void
    {
        $service = new ColorConversionService(new RGB('255,0,0'));

        $this->assertSame(['init'], $this->stackStepNames($service->conversionStack()));

        $service->convert();

        $this->assertContains('convert_start', $this->stackStepNames($service->conversionStack()));
    }

    /**
     * @param list<string> $stack
     * @return list<string>
     */
    private function stackStepNames(array $stack): array
    {
        return array_map(
            static fn (string $entry): string => trim(explode('|', $entry)[0]),
            $stack,
        );
    }

    private function normalizeHex(string $hex): string
    {
        return strtoupper(str_replace('#', '', $hex));
    }
}
