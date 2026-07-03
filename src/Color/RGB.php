<?php

declare(strict_types=1);

namespace wwaz\ColorService\Color;

use wwaz\Colormodel\Model\RGB as RGBModel;
use wwaz\ColorService\Traits\HasColorspace;

class RGB extends RGBModel
{
    use HasColorspace;

    protected $type = 'rgb';

    protected $colorSpace = 'rgb';

    protected $keys = ['r', 'g', 'b'];

    protected $keysMinMax = [
        'r' => [0, 255],
        'g' => [0, 255],
        'b' => [0, 255],
    ];

    protected $value = [];
}
