<?php

declare(strict_types=1);

namespace wwaz\ColorService\Traits;

trait HasColorspace
{
    public function colorSpace(): string
    {
        return $this->colorSpace;
    }

    public function toColorStringPercentage(): string
    {
        $values = $this->toArray();
        $vals = [];
        foreach ($values as $val) {
            if (is_float($val) && abs($val) <= 1) {
                $val = $val * 100;
            }
            $vals[] = $val . '%';
        }

        return $this->type . '(' . implode(',', $vals) . ')';
    }
}
