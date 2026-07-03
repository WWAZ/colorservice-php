<?php

declare(strict_types=1);

namespace wwaz\ColorService\DTO;

/**
 * @phpstan-type GivenShape array{colorSpace: string, representation: string, value: string}
 * @phpstan-type ColorValuesShape array<string, string>
 * @phpstan-type SchemeSwatchShape array{cmyk: string, hex: string, rgb: string, rgb_math: string, given?: string}
 * @phpstan-type BridgeResultShape array{
 *     colorWorkSpace: array{rgb: ?string, cmyk: ?string},
 *     intent: string,
 *     given: GivenShape,
 *     values: ColorValuesShape,
 *     perception: ColorValuesShape,
 *     profiled: ColorValuesShape,
 *     inField: ColorValuesShape,
 *     name: string,
 *     conversionStack: list<string>,
 *     schemes?: array<string, list<SchemeSwatchShape>>
 * }
 */
class ICCConversionResultV1 extends ICCConversionResultDTO
{
    /** @param BridgeResultShape $data */
    public function __construct(public array $data) {}

    /** @return BridgeResultShape */
    public function toArray(): array
    {
        return $this->data;
    }

    public function name(): string
    {
        return $this->data['name'];
    }

    public function hexPerception(): string
    {
        return $this->data['perception']['hex'];
    }

    public function rgbPerception(): string
    {
        return $this->data['perception']['rgb'];
    }

    public function rgb(): string
    {
        return $this->data['values']['rgb'];
    }

    public function hex(): string
    {
        return $this->data['values']['hex'];
    }

    public function cmyk(): string
    {
        return $this->data['values']['cmyk'];
    }

    /** @return GivenShape */
    public function given(): array
    {
        return $this->data['given'];
    }

    public function givenColorspace(): string
    {
        return $this->given()['colorSpace'];
    }

    public function givenType(): string
    {
        return $this->given()['representation'];
    }

    public function givenValue(): string
    {
        return $this->given()['value'];
    }
}
