# Changelog

All notable changes to this project will be documented in this file.

## 1.0.0 - 2026-07-01

### Added

- Initial standalone release of the color service library (`wwaz/colorservice-php`).
- `Bridge` for profiled RGB/CMYK conversion, perception preview and color schemes.
- `BridgeService` as a string-based facade for HTTP/API integrations.
- `BridgeConfig` for explicit defaults without Laravel or env dependencies.
- `IccColorConverter` for ICC profile resolution and batch conversion.
- Package-local PHPUnit test suite and release documentation.

### Changed

- `BridgeService` accepts optional profile/intent arguments and falls back to `BridgeConfig` defaults.
- Removed `config/color-service.php`; configuration belongs in consuming apps (e.g. Cooler's `config/cooler.php`).

### Notes

- ICC conversion uses the global engine state from `wwaz/colorconvert-php`. Use one converter instance per request/workflow when running concurrent conversions.
