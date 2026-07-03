<?php

declare(strict_types=1);

namespace wwaz\ColorService\Color;

use wwaz\Colormodel\Model\CMYKInt as CMYKModel;
use wwaz\ColorService\Traits\HasColorspace;

class CMYK extends CMYKModel
{
    use HasColorspace;

    protected $type = 'cmyk';

    protected $colorSpace = 'cmyk';

    protected $keys = ['c', 'm', 'y', 'k'];

    protected $keysMinMax = [
        'c' => [0, 100],
        'm' => [0, 100],
        'y' => [0, 100],
        'k' => [0, 100],
    ];

    protected $value = [];

    /**
     * Avoid vendor CMYKInt::__get debug trap while keeping property access stable.
     */
    public function __get($key)
    {
        if (! is_string($key)) {
            return null;
        }

        $canonical = match ($key) {
            'c' => 'cyan',
            'm' => 'magenta',
            'y' => 'yellow',
            'k' => 'key',
            default => $key,
        };

        if (! in_array($canonical, ['cyan', 'magenta', 'yellow', 'key'], true)) {
            return null;
        }

        $value = $this->{$canonical} ?? null;

        if (in_array($key, ['c', 'm', 'y', 'k'], true) && is_numeric($value)) {
            $value = (float) $value;

            return abs($value) <= 1
                ? (int) round($value * 100)
                : (int) round($value);
        }

        return $value;
    }
}
