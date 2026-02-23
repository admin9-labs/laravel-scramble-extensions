# Changelog

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
