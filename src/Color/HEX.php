<?php

declare(strict_types=1);

namespace wwaz\ColorService\Color;

use wwaz\Colormodel\Model\Hex as HEXModel;
use wwaz\ColorService\Traits\HasColorspace;

class HEX extends HEXModel
{
    use HasColorspace;

    protected $type = 'hex';

    protected $colorSpace = 'rgb';

    protected $keys = [];

    protected $value = '';

    public function __construct(string $hex)
    {
        if (! str_contains($hex, '#')) {
            $hex = '#' . $hex;
        }
        $this->hex = $hex;
    }

    public function toString($sep = ','): string
    {
        return $this->hex;
    }

    public function toColorString(): string
    {
        return $this->hex;
    }
}
