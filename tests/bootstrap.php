<?php

declare(strict_types=1);

require dirname(__DIR__) . '/vendor/autoload.php';

use wwaz\Colorprofile\Console\ColorprofileCliConfig;
use wwaz\Colorprofile\Config\ConfigStore;
use wwaz\Colorprofile\ProfileInstaller;
use wwaz\Colorprofile\ProfileManifest;

$monorepoRoot = dirname(__DIR__, 4);

if (is_file($monorepoRoot . '/.colorprofile.config.json')) {
    ColorprofileCliConfig::setRootOverrideForTesting($monorepoRoot);

    return;
}

$fixtureRoot = __DIR__ . '/fixtures/colorprofile';
ColorprofileCliConfig::setRootOverrideForTesting($fixtureRoot);

if (! is_dir($fixtureRoot)) {
    mkdir($fixtureRoot, 0775, true);
}

if (! is_file($fixtureRoot . '/.colorprofile.config.json')) {
    ConfigStore::initialize();
}

$profileBaseDir = $fixtureRoot . '/storage/profiles';
$profiles = [
    'rgb/sRGB_v4_ICC_preference.icc' => 'sRGB_v4_ICC_preference',
    'cmyk/ISOcoated_v2_300_eci.icc' => 'ISOcoated_v2_300_eci',
];

$installer = new ProfileInstaller(new ProfileManifest());

foreach ($profiles as $relativePath => $profileName) {
    if (is_file($profileBaseDir . '/' . $relativePath)) {
        continue;
    }

    $installer->installRegistryProfile($profileName, profileBaseDir: $profileBaseDir);
}
