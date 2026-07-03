# Color Service PHP

Convert colors between RGB and CMYK using real ICC profiles, not just math formulas.

This library is useful when screen colors and print colors should match as closely as possible. It also returns a perception preview, a color name, contrast helpers, and optional color schemes.

Input types: `rgb`, `cmyk`, `hex`.

---

## Installation

```bash
composer require wwaz/colorservice-php
```

Dependencies:

- `wwaz/colorconvert-php`
- `wwaz/colorprofile-php`
- `wwaz/colormodel-php`
- `wwaz/colorname-php`

### ICC Profiles Setup

Profile names like `sRGB_v4_ICC_preference` are resolved through `wwaz/colorprofile-php`. Before first use, install the profiles once:

```bash
vendor/bin/colorprofile init
vendor/bin/colorprofile install sRGB_v4_ICC_preference ISOcoated_v2_300_eci
```

---

## Quick Start

Use `ColorService` when you receive colors as strings and want the full result with profiles, perception preview, and names.

```php
use wwaz\ColorService\ColorService;

$service = new ColorService(
    rgbProfile: 'sRGB_v4_ICC_preference',
    cmykProfile: 'ISOcoated_v2_300_eci',
);

$result = $service->ICCConvert('255,0,0');

echo $result->hex();        // e.g. "#FF0000"
echo $result->rgb();        // "255,0,0"
echo $result->cmyk();       // profiled CMYK values
echo $result->perception(); // screen preview after the ICC roundtrip
echo $result->name();       // closest color name
```

Accepted string formats:

- HEX: `f00`, `#ff0000`, `009EE3`
- RGB: `255,0,0`
- CMYK: `0,100,100,0`

You can also pass color objects:

```php
use wwaz\ColorService\Color\CMYK;

$result = $service->ICCConvert(new CMYK('0,100,100,0'));
```

---

## Use Cases

### 1. Convert Screen RGB to Print CMYK

```php
$result = $service->ICCConvert('255,0,0');

echo $result->cmyk();
echo $result->perception();
```

### 2. Convert Print CMYK Back to Screen RGB

```php
$result = $service->ICCConvert('53,0,60,29');

echo $result->rgb();
echo $result->hex();
echo $result->name();
```

### 3. Convert Multiple Colors

```php
$results = $service->ICCConvertBatch([
    'f00',
    '255,255,0',
    '100,0,0,0',
    '009EE3',
]);
```

### 4. Generate Color Schemes

Generate complementary, triadic, analogous, square, tetradic, tint, shade, tone, and hue palettes.

```php
$schemes = $service->schemes('0,158,227');

$complementary = $schemes['complementary'];
$firstSwatch = $complementary[0];

echo $firstSwatch['hex'];
echo $firstSwatch['rgb'];
echo $firstSwatch['cmyk'];
```

To read one named scheme:

```php
$tints = $service->schema('0,158,227', 'tint');
```

### 5. Text Color and Lightness Helpers

```php
$service->isLight('ffffff');                     // true
$service->isDark('000000');                      // true
$service->getReadableTextColor('009EE3');            // "#000000" or "#FFFFFF"
$service->getReadableTextColorKeepingHue('009EE3', 2.1); // adjusted HEX text color
```

---

## Lower-Level Conversion

Use `ColorConversionService` when you already have a color model and need control over DTO version, profiles, intent, or scheme inclusion.

```php
use wwaz\ColorService\Color\RGB;
use wwaz\ColorService\Processing\ColorConversionService;

$conversion = new ColorConversionService(new RGB('255,0,0'));
$conversion->setProfile('rgb', 'sRGB_v4_ICC_preference');
$conversion->setProfile('cmyk', 'ISOcoated_v2_300_eci');
$conversion->setIntent('perceptual');

$result = $conversion->convert();
```

Include schemes directly in the V2 result:

```php
$result = (new ColorConversionService(new RGB('0,158,227')))
    ->withIncludeSchemes()
    ->profiled(
        rgbProfile: 'sRGB_v4_ICC_preference',
        cmykProfile: 'ISOcoated_v2_300_eci',
    );

$data = $result->toArray();
```

Return the legacy V1 DTO shape:

```php
$conversion->setAcceptICCConversionDTO('v1');
$legacyResult = $conversion->convert();
```

---

## ICC Conversion Only

Use `IccColorConverter` when you only need RGB ↔ CMYK conversion, without names, DTOs, metrics, or schemes.

```php
use wwaz\ColorService\Color\CMYK;
use wwaz\ColorService\Color\RGB;
use wwaz\ColorService\Processing\IccColorConverter;

$converter = new IccColorConverter();
$converter->setProfile('rgb', 'sRGB_v4_ICC_preference');
$converter->setProfile('cmyk', 'ISOcoated_v2_300_eci');

$cmyk = $converter->rgbToCmyk(new RGB('255,0,0'));
$rgb = $converter->cmykToRgb(new CMYK('0,100,100,0'));
```

Batch helpers are available:

```php
$cmyks = $converter->rgbToCmykBatch([
    new RGB('255,0,0'),
    new RGB('0,158,227'),
]);
```

---

## Main Classes

| Class | When to use |
|-------|-------------|
| `ColorService` | String-friendly facade for apps, controllers, and APIs |
| `ColorConversionService` | Full conversion workflow for color model objects |
| `IccColorConverter` | Low-level RGB ↔ CMYK conversion only |
| `ColorMetrics` | Lightness, contrast, and readable text color helpers |
| `ICCConversionResultV2` | Default slim conversion result |
| `ICCConversionResultV1` | Legacy detailed conversion result |

---

## Rendering Intent

The rendering intent tells the ICC engine how to map colors when the source and target profile cannot represent the same colors.

Supported values:

- `relative` (default)
- `perceptual`
- `saturation`
- `absolute`

```php
$service = new ColorService(
    rgbProfile: 'sRGB_v4_ICC_preference',
    cmykProfile: 'ISOcoated_v2_300_eci',
    intent: 'perceptual',
);
```

### Which Intent Should I Use?

| Intent | Best for | What it does in practice |
|--------|----------|--------------------------|
| `relative` | Everyday print prep, logos, layouts, brand colors | Keeps neutral grays stable and adjusts other colors relative to white. Usually the safest default. |
| `perceptual` | Photos, gradients, images, smooth backgrounds | Compresses the whole color range so the image still looks natural. |
| `saturation` | Charts, infographics, bold graphics | Tries to keep vivid colors strong, even if hue and lightness shift a bit. |
| `absolute` | Proofing and strict simulation | Maps colors more literally to the destination profile. Useful for color-accurate previews. |

If no ICC profiles are set on the lower-level services, the library falls back to math conversion and the intent has no visible effect.

---

## Result Structures

### Default V2 Result

`ICCConversionResultV2::toArray()` returns a slim payload:

```php
[
    'given' => [
        'colorSpace' => 'rgb',
        'representation' => 'rgb',
        'value' => '255,0,0',
    ],
    'hex' => '#FF0000',
    'rgb' => '255,0,0',
    'cmyk' => '0,100,100,0',
    'perception' => '#EF0000',
    'name' => 'Red',
    'schemes' => [], // only when requested
]
```

Helper methods:

```php
$result->hex();
$result->rgb();
$result->cmyk();
$result->perception();
$result->name();
$result->toArray();
```

### Legacy V1 Result

Pass `acceptICCConversionDTO: 'v1'` to `ColorService` or call `setAcceptICCConversionDTO('v1')` on `ColorConversionService`.

The V1 payload includes `values`, `perception`, `profiled`, `inField`, `colorWorkSpace`, `intent`, and `conversionStack`.

```php
$service = new ColorService(
    rgbProfile: 'sRGB_v4_ICC_preference',
    cmykProfile: 'ISOcoated_v2_300_eci',
    acceptICCConversionDTO: 'v1',
);

$result = $service->ICCConvert('255,0,0');

echo $result->hexPerception();
echo $result->givenValue();
```

---

## Error Handling

- Unknown profile name: `InvalidArgumentException`
- Unknown color type: `InvalidArgumentException`
- Unknown scheme name: `InvalidArgumentException`
- Invalid color values: validated by `wwaz/colormodel-php`

---

## Important Note About Concurrency

`IccColorConverter` uses the global engine state from `wwaz/colorconvert-php`. For parallel work, use one converter instance per request or workflow.

---

## Tests

```bash
composer install
vendor/bin/colorprofile init
vendor/bin/colorprofile install sRGB_v4_ICC_preference ISOcoated_v2_300_eci
composer test
```

When running inside the cooler monorepo, existing root profiles are picked up automatically.

---

## Laravel

For a ready-made Laravel integration, see [`wwaz/cooler`](https://github.com/WWAZ/cooler).

The library itself has no `config()` or `env()` calls. Pass profile names and intent explicitly through `ColorService` or configure them on `ColorConversionService`.

---

## License

MIT, see [LICENSE](LICENSE).
