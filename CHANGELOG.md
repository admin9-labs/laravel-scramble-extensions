# Changelog

## v0.4.0 - 2026-10-23

### Added
- `ReadOnlyIntegerType` emits the standard `readOnly: true` keyword while preserving integer constraints. Reuse `ReadOnlySerialization` in other explicit Scramble type subclasses.
- `ClosedObjectType` emits `additionalProperties: false` while preserving properties, required fields, nested objects, references and clone isolation. Conflicting typed additional properties are rejected with `LogicException` during serialization.
- CI coverage for Scramble 0.13.30, 0.13.49 and the latest version allowed by `^0.13.30`, including legacy and OpenAPI 3.1/3.2 serialization paths.

### Fixed
- Normalize object-valued serialization results before adding `readOnly`, so `MixedType` subclasses no longer throw `TypeError`. Regression tests cover all three serializer entrypoints and complete OpenAPI documents.

### Compatibility and upgrade
- Requirements remain PHP `^8.3` and Scramble `^0.13.30`.
- Both helpers are opt-in documentation features; they do not change runtime validation or automatically infer read-only fields. Nested objects must explicitly use `ClosedObjectType` to reject extra properties.
- Preserve the custom type instances: copying base properties into an ordinary type with `addProperties()` does not copy subclass serialization behavior.
- Applications pinned to `^0.3` must explicitly allow the new minor version:

```bash
composer require admin9/laravel-scramble-extensions:^0.4
```

Merged changes: [#1](https://github.com/admin9-labs/laravel-scramble-extensions/pull/1), [#2](https://github.com/admin9-labs/laravel-scramble-extensions/pull/2).

[Full changelog](https://github.com/admin9-labs/laravel-scramble-extensions/compare/v0.3.2...v0.4.0)

## v0.3.2 - 2026-06-30

### Added
- Add configurable business response schema overrides for fields Scramble cannot infer precisely.

### Fixed
- Document supported business error responses with their HTTP status codes instead of collapsing every error response to 200.

## v0.3.1 - 2026-06-30

### Fixed
- Preserve pagination metadata in business response envelopes for paginated resource collection responses.

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
