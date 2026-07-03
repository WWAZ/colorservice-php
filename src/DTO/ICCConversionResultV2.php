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
class ICCConversionResultV2 extends ICCConversionResultDTO
{
    public function __construct(public array $data) {}

    public function name(): string
    {
        return $this->data['name'];
    }

    public function perception(): string
    {
        return $this->data['perception'];
    }

    public function rgb(): string
    {
        return $this->data['rgb'];
    }

    public function hex(): string
    {
        return $this->data['hex'];
    }

    public function cmyk(): string
    {
        return $this->data['cmyk'];
    }

    public function toArray()
    {
        return $this->data;
    }
}
