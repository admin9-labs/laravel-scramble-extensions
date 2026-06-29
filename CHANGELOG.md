# Changelog

## v0.3.0 - 2026-06-30

### Added
- Laravel 13 support with Scramble `^0.13.30` as the baseline.
- PHPUnit/Testbench coverage for business response envelopes, Mitoop scene form requests, Mitoop filter query parameters, and model column comments.
- README positioning that identifies this package as a Mitoop ecosystem adapter rather than a general-purpose Scramble extension.

### Changed
- Require PHP `^8.3` for the Laravel 13 support line.
- Upgrade development dependencies to Testbench `^11.0` and PHPUnit `^11.5 || ^12.5`.

## v0.2.0 - 2026-02-24

### Added
- `ModelColumnDescriptionExtension` — automatically reads database column comments and attaches them as OpenAPI property descriptions
- `column_comments` config option to enable/disable column comment extraction (default: `true`)
- `resetCache()` methods on `BusinessResponseInferExtension` and `FilterQueryParametersExtractor` for testability

### Fixed
- Safer reference resolution in `BusinessResponseOperationExtension` with try/catch to prevent crashes on unresolvable references
- Use `hasProperty()` check before `getProperty()` in paginator wrapping to avoid potential errors

### Changed
- Cached trait resolution in `BusinessResponseInferExtension` for better performance

## v0.1.0 - 2025-06-01

### Added
- Initial release
- `BusinessResponseInferExtension` — infer return types for business response trait
- `BusinessResponseOperationExtension` — wrap OpenAPI responses with success/error envelope
- `SceneFormRequestParametersExtractor` — scene-based form request parameter extraction
- `FilterQueryParametersExtractor` — filter query parameter extraction
