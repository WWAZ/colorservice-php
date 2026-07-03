<?php

declare(strict_types=1);

namespace wwaz\ColorService;

use wwaz\Colormodel\Model\Color as ColorModel;
use wwaz\Colorname\ColornameService;
use wwaz\ColorService\Processing\ColorConversionService;
use wwaz\ColorService\DTO\ICCConversionResult;
use wwaz\ColorService\DTO\ICCConversionResultDTO;
use wwaz\ColorService\Color\CMYK;
use wwaz\ColorService\Color\HEX;
use wwaz\ColorService\Color\RGB;
use wwaz\ColorService\DTO\BridgeResult;
use wwaz\ColorService\Processing\ColorMetrics;

class ColorService
{
    public readonly string $rgbProfile;

    public readonly string $cmykProfile;

    public string $colortype;

    public string $colorvalue;

    public readonly ?string $intent;

    public readonly ?string $acceptICCConversionDTO;

    public function __construct(
        string $rgbProfile,
        string $cmykProfile,
        ?string $intent = null,
        public ?ColornameService $colornameService = null,
        ?string $acceptICCConversionDTO = null
    ){
        $this->rgbProfile = $rgbProfile;
        $this->cmykProfile = $cmykProfile;
        $this->colornameService = new ColornameService();
        $this->intent = $intent ?? 'relative';
        $this->acceptICCConversionDTO = $acceptICCConversionDTO ?? null;
    }

    /** 
     * Converts color by predefined icc profiles.
     * 
     * @param string|object $color
     * @return ICCConversionResultDTO
    */
    public function ICCConvert(string|object $color): ICCConversionResultDTO
    {
        if( is_string($color) ){
            $color = $this->fromString($color);
        }
        $this->colortype = $color->type();
        $this->colorvalue = $color->toString();
        return $this->createColorConversionService()
            ->profiled($this->rgbProfile, $this->cmykProfile, false);
    }

    /** 
     * Converts colors by predefined icc profiles.
     * 
     * @param string|object $color
     * @return array
    */
    public function ICCConvertBatch(array $colors)
    {
        $result = [];
        foreach($colors as $index => $color){
            $result[] = $this->ICCConvert($color);
        }
        return $result;
    }

    /** 
     * Returns color name.
     * 
     * @param string|object $color
     * @return bool
    */
    public function name(string|object $color): string
    {
        return $this->ICCConvert($color)->name();
    }

    /** 
     * Returns true, when given color ist light.
     * 
     * @param string|object $color
     * @return bool
    */
    public function isLight(string|object $color): bool
    {
        $hex = $this->ICCConvert($color)->perception();
        return (new ColorMetrics)->isLight($hex);
    }

    /** 
     * Returns true, when given color ist dark.
     * 
     * @param string|object $color
     * @return bool
    */
    public function isDark(string|object $color): bool
    {
        $hex = $this->ICCConvert($color)->perception();
        return (new ColorMetrics)->isDark($hex);
    }


    /**
     * Ermittelt eine lesbare Textfarbe (Schwarz oder Weiß)
     * für einen Hintergrund.
     *
     * @param string|object $color
     * @return string '#000000' oder '#FFFFFF'
     */
    public function getReadableTextColor(string|object $color): string
    {
        $hex = $this->ICCConvert($color)->perception();
        return (new ColorMetrics)->getReadableTextColor($hex);
    }

    /**
     * Ermittelt eine lesbare Textfarbe mit beibehaltenem Farbton,
     * indem nur die Lightness angepasst wird.
     *
     * Standard: WCAG AAA (7:1)
     *
     * @param string $backgroundHex Hintergrundfarbe
     * @param float  $minContrast Mindestkontrast (Standard: 2.1 = AAA)
     * @return string HEX-Farbe
     */
    public function getReadableTextColorKeepingHue(string|object $color, float $minContrast = 2.1): string
    {
        $hex = $this->ICCConvert($color)->perception();
        return (new ColorMetrics)->getReadableTextColorKeepingHue($hex, $minContrast);
    }

    /**
     * Returns color schemes for the given color.
     *
     * @param string|object $color
     * @return array<string, list<array{cmyk: string, hex: string, rgb: string, rgb_math: string, given?: string}>>
     */
    public function schemes(string|object $color, bool $profiled = true): array
    {
        if (is_string($color)) {
            $color = $this->fromString($color);
        }
        $this->colortype = $color->type();
        $this->colorvalue = $color->toString();

        $bridge = $this->createColorConversionService();
        $bridge->setProfile('rgb', $this->rgbProfile);
        $bridge->setProfile('cmyk', $this->cmykProfile);
        $bridge->convert();

        return $bridge->schemes($profiled);
    }

    /**
     * Returns a single color schema for the given color.
     *
     * @param string|object $color
     * @return list<array{cmyk: string, hex: string, rgb: string, rgb_math: string, given?: string}>
     */
    public function schema(string|object $color, string $name, bool $profiled = true): array
    {
        $schemes = $this->schemes($color, $profiled);

        if (! isset($schemes[$name])) {
            throw new \InvalidArgumentException('Unknown schema "' . $name . '"');
        }

        return $schemes[$name];
    }

    /**
     * Creates color model from given color string.
     * 
     * @param string $str – ex. 'fff', '255,0,0', '100,0,0,0'
     * @return ColorModel
     */
    protected function fromString(string $str): ColorModel
    {
        $str = str_replace(' ', '', $str);

        if( strpos($str, ',') !== false ){

            $cnt = count(explode(',', $str));

            if( $cnt === 4 ){
                return new CMYK($str);
            }

            if( $cnt === 3 ){
                return new RGB($str);
            }
        }

        return new Hex($str);
    }
    
    /**
     * Returns Bridge.
     */
    protected function createColorConversionService(): ColorConversionService
    {
        $converter = new ColorConversionService($this->createColor(), $this->colornameService);
        $converter->setAcceptICCConversionDTO($this->acceptICCConversionDTO);
        $converter->setIntent($this->intent);

        return $converter;
    }

    protected function createColor(): ColorModel
    {
        return match ($this->colortype) {
            'cmyk' => new CMYK($this->colorvalue),
            'hex' => new HEX($this->colorvalue),
            'rgb' => new RGB($this->colorvalue),
            default => throw new \InvalidArgumentException('Color type "' . $this->colortype . '" is unknown'),
        };
    }
}