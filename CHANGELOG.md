# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [0.2.0] - 2026-06-29

Pre-1.0: still subject to change between minor versions.

### Added
- **Symfony Messenger integration** (`providers.messenger`, needs `symfony/messenger`),
  built on Messenger's own routing semantics. Two opt-in capabilities:
  - `enrichment` — derives servers (from transport DSNs), content types (from the
    transport serializer) and channel↔server links for documented messages;
  - `discovery` — discovers messages straight from the routing map (FQCN keys plus
    interface/parent/namespace-wildcard matches), so routed classes need no attribute.

  A `transports` allowlist scopes both; failure/retry transports are always excluded.

### Changed
- **BREAKING (config):** the `discovery` root was renamed to `providers`, so
  `discovery.attribute.paths` is now `providers.attribute.paths`.
- `#[AsyncApiMessage]` `contentType` now defaults to `null` instead of
  `'application/json'`. The rendered document is unchanged (still `application/json`
  when unset); `null` lets a routed Messenger transport's serializer determine it.

## [0.1.0] - 2026-06-21

First public release. Pre-1.0: the API may still change between minor versions.

### Added
- `#[AsyncApiMessage]` attribute to document a message DTO: placement (`channel`,
  `action`) plus message metadata (`name`, `title`, `summary`, `description`,
  `contentType`, `tags`, `externalDocs`, `correlationId`).
- AsyncAPI **3.0.0** and **3.1.0** support (default 3.1.0); the targeted version
  is validated.
- Typed document model covering the full standard structure (info, servers,
  channels, operations, messages, components, and shared value objects), each
  object keeping unmodelled fields (bindings, security, schemas…) in a local
  passthrough; hydrated from a static config fragment via `applyArray()` and
  rendered with `toArray()`.
- Extensible processor pipeline (`AsyncApiProcessorInterface` + `DocumentContext`)
  as the general extension point; built-ins: config merge, attribute discovery,
  payload derivation, message canonicalization into `components.messages`, and
  version validation.
- Message discovery via the `MessageProviderInterface` SPI, with a built-in
  token-based attribute scanner (PSR-4-agnostic).
- Payload schemas derived from each DTO through
  [`zeusi/json-schema-extractor`](https://github.com/antonioturdo/json-schema-extractor)
  (optional), with an `ExtractionContextFactory` seam to pass per-message
  serialization context (e.g. groups).
- `GET /asyncapi.json` (always available) and a `GET /asyncapi` HTML UI rendered
  with the AsyncAPI web component (requires `symfony/twig-bundle`).
- Configuration: static `document` fragment, `discovery.attribute.paths`,
  `payload_schema_extractor` (service id + optional context factory, with a
  bare-string shorthand), and `ui` options.
- Optional PSR-3 diagnostics (duplicate message names, extraction failures), and
  generated documents validated against the official 3.0.0/3.1.0 JSON Schemas in
  the test suite.
