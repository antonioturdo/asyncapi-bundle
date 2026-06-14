# AsyncAPI Bundle

[![Packagist Version](https://img.shields.io/packagist/v/zeusi/asyncapi-bundle.svg)](https://packagist.org/packages/zeusi/asyncapi-bundle)
[![PHP](https://img.shields.io/badge/php-%3E%3D8.1-777bb4.svg)](https://www.php.net/)
[![CI](https://github.com/antonioturdo/asyncapi-bundle/actions/workflows/ci.yml/badge.svg)](https://github.com/antonioturdo/asyncapi-bundle/actions/workflows/ci.yml)
[![PHPStan](https://img.shields.io/badge/phpstan-level%208-brightgreen.svg)](https://phpstan.org/)
[![Coverage](https://codecov.io/gh/antonioturdo/asyncapi-bundle/graph/badge.svg)](https://codecov.io/gh/antonioturdo/asyncapi-bundle)
[![License](https://img.shields.io/packagist/l/zeusi/asyncapi-bundle.svg)](LICENSE)

> Symfony bundle to generate AsyncAPI 3.x documentation from your message DTOs, always in sync with the code.

Unlike REST API tooling, which can enumerate controllers and routes, an
event-driven app offers no natural anchor for discovering its messages — and
AsyncAPI doesn't impose one. So the bundle takes the simplest pragmatic route:
each message is declared with an attribute on its DTO. Its payload schema is
derived from the PHP class (via
[`zeusi/json-schema-extractor`](https://github.com/antonioturdo/json-schema-extractor))
and assembled into a valid [AsyncAPI](https://www.asyncapi.com) 3.x document.
Because the schema comes from the code, changing a DTO changes the docs.

## Features

- **Code-derivable AsyncAPI 3.x** — messages, channels, operations and reusable `components.messages`, assembled from your DTOs.
- **Payload schemas from your PHP** — derived from native types, PHPDoc, Symfony Validator constraints and Serializer metadata (via [`zeusi/json-schema-extractor`](https://github.com/antonioturdo/json-schema-extractor)).
- **AsyncAPI 3.x** — supports the 3.0.0 and 3.1.0 versions; defaults to 3.1.0.
- **Two endpoints** — `GET /asyncapi.json` serves the document as JSON, `GET /asyncapi` renders it as an HTML UI.
- **Extensible by design** — pluggable message sources and a processor pipeline to add or refine anything in the document.
- **Bring your own extraction** — swap the payload extractor, and supply a per-message extraction context (e.g. serialization groups).

## Requirements

- PHP 8.1+
- Symfony 6.4, 7.x, or 8.x

## Installation

```bash
composer require zeusi/asyncapi-bundle
```

If you don't use Symfony Flex, register the bundle in `config/bundles.php`:

```php
return [
    // ...
    Zeusi\AsyncApiBundle\AsyncApiBundle::class => ['all' => true],
];
```

Minimal configuration — just your document's `info`:

```yaml
# config/packages/asyncapi.yaml
asyncapi:
  document:
    info:
      title: 'Wanderlust Events'
      version: '1.0.0'
```

## Declare a message

Mark each message DTO with `#[AsyncApiMessage]`:

```php
use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;

#[AsyncApiMessage]
final class TripBooked
{
    public function __construct(
        public readonly string $bookingId,
        public readonly int $travelers,
    ) {
    }
}
```

The bundle scans your code for `#[AsyncApiMessage]`, derives each payload schema
from the DTO, and assembles a valid AsyncAPI 3.x document. You'll typically add a
`channel` (and other metadata) — see the [attribute reference](#attribute-reference).

## Expose the document and UI

Import the bundle's controller routes:

```yaml
# config/routes/asyncapi.yaml
asyncapi:
  resource: '@AsyncApiBundle/Controller/AsyncApiController.php'
  type: attribute
```

You now have:

- `GET /asyncapi.json` — the generated AsyncAPI document as JSON. Always available.
- `GET /asyncapi` — an HTML UI rendered with the AsyncAPI web component. This one
  needs Twig: install `symfony/twig-bundle` to enable it.

## Configuration reference

```yaml
asyncapi:
  # Static AsyncAPI fragment merged into the generated document. Put any static
  # parts here — info, servers, security, tags, externalDocs…: modelled fields are
  # folded into the typed model, anything else is kept verbatim. The common ones:
  document:
    # Spec version. Optional; defaults to the latest supported (3.1.0).
    # Supported: 3.0.0, 3.1.0. An unsupported value is rejected.
    asyncapi: '3.1.0'
    info:
      title: 'Wanderlust Events'
      version: '1.0.0'
      description: 'Events Wanderlust publishes to its shared broker.'
    servers:
      production:
        host: 'broker.wanderlust.example:5672'
        protocol: 'amqp'

  # How messages are discovered. `attribute` is the built-in source that scans for
  # #[AsyncApiMessage] classes; its paths default to the project's PSR-4 roots.
  discovery:
    attribute:
      paths:
        - '%kernel.project_dir%/src/Message'

  # Payload schema derivation (see "Payload schemas"). Shorthand: a bare string
  # `payload_schema_extractor: 'app.my_extractor'` sets just the service id.
  payload_schema_extractor:
    # Service id of the SchemaExtractor. Defaults to a built-in one
    # (Symfony Serializer when available, else json_encode).
    service: 'app.my_schema_extractor'
    # Optional service implementing Payload\ExtractionContextFactory: builds the
    # ExtractionContext (e.g. serialization groups) passed to the extractor per
    # message. Omit it (the default) to call the extractor without a context.
    context_factory: 'app.my_context_factory'

  # Options forwarded to the AsyncAPI web component (the HTML UI).
  ui:
    config:
      show:
        sidebar: true
        errors: true
    css_import_path: 'https://unpkg.com/@asyncapi/react-component@3.1.3/styles/default.min.css'
```

## Attribute reference

`#[AsyncApiMessage]` carries the message-level documentation and its placement.

| Argument      | Type              | Default            | Purpose |
|---------------|-------------------|--------------------|---------|
| `channel`     | `?string`         | `null`             | Grouping key; `null` gives the message a channel of its own. |
| `action`      | `OperationAction` | `Send`             | `Send` (the app publishes) or `Receive` (the app consumes). |
| `name`        | `?string`         | class short name   | Message name / components key. |
| `title`       | `?string`         | the message name   | Human title (shown in the UI). |
| `summary`     | `?string`         | `null`             | One-line summary. |
| `description` | `?string`         | `null`             | Longer description (CommonMark). |
| `contentType` | `string`          | `application/json` | Payload content type. |
| `tags`        | `list<string\|Tag>` | `[]`             | Free-form tags; a plain string is shorthand for `new Tag(name: …)`. |
| `externalDocs` | `?ExternalDocumentation` | `null`     | Link to external documentation for this message. |
| `correlationId` | `?CorrelationId` | `null`            | Where the correlation id lives in the message (a runtime expression). |

> The message name defaults to the class short name. For a stable public
> contract, set an explicit `name` so renaming the PHP class doesn't change it
> (a duplicate name across two classes is reported as a warning).

The richer arguments take value objects, constructed inline in the attribute:

```php
use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;
use Zeusi\AsyncApiBundle\Document\CorrelationId;
use Zeusi\AsyncApiBundle\Document\ExternalDocumentation;
use Zeusi\AsyncApiBundle\Document\Tag;

#[AsyncApiMessage(
    channel: 'bookings',
    summary: 'A trip was booked',
    tags: ['travel', new Tag(name: 'public', description: 'Part of the public contract')],
    externalDocs: new ExternalDocumentation(url: 'https://docs.wanderlust.example/events/trip-booked'),
    correlationId: new CorrelationId(location: '$message.header#/bookingId'),
)]
final class TripBooked
{
    // ...
}
```

## What it produces

The generation is an opinionated, code-derivable subset of AsyncAPI (the model
can express more — see [Discovery and extending](#discovery-and-extending)):

- Each DTO marked with `#[AsyncApiMessage]` becomes a **message** placed in
  `components.messages` and referenced via `$ref`.
- Messages are grouped into **channels** by their `channel`; a message with no
  `channel` gets a channel of its own (keyed by the message `name`).
- Each `(message, action)` yields one **operation**.
- Static parts from `document` (`info`, `servers`, …) are merged underneath.

## Payload schemas

Payloads are derived from your DTOs by
[`zeusi/json-schema-extractor`](https://github.com/antonioturdo/json-schema-extractor).
The bundle wires a default `SchemaExtractor` for you — no setup required:

- it uses the **Symfony Serializer** strategy when the Serializer is available, so
  serialization groups, `#[SerializedName]`, name converters and discriminators
  are reflected (the shape that actually goes over the wire), falling back to
  `json_encode` otherwise;
- PHPDoc and Symfony Validator enrichers are added when their packages are present.

To use your own, register it as a service and set the
`payload_schema_extractor.service` config key to its id.

The serialization context is decided at extraction time, not baked into the DTO:
*which* serialization groups (or other `ExtractionContext` capabilities) apply is
a per-message choice that must reach the extractor.
Supply it by implementing `Payload\ExtractionContextFactory` and wiring it via
`payload_schema_extractor.context_factory`; its `create(ExtractionTarget,
DocumentContext)` returns the `ExtractionContext` passed to the extractor (or
`null` for none). This keeps extractor-specific knowledge (e.g. which Symfony
Serializer groups select a payload's shape) in your code, not in the bundle.

## Discovery and extending

Declaring messages with `#[AsyncApiMessage]` is the default flow — and today the
only built-in one — but it needn't stay that way: the document is built by an
extensible pipeline, so messages can come from other sources and the output can
be refined by your own code. (The reason for the attribute approach — the lack of
a natural anchor for events — is covered above.)

**The general extension point is the processor.** Implement
`Zeusi\AsyncApiBundle\Processor\AsyncApiProcessorInterface` (services tagged
`asyncapi.processor` — autoconfigured — ordered by priority) to add or refine
*anything* in the document: channels, operations, messages, top-level fields. It
receives the whole document plus a context, so it can build straight from
operations, a channel registry, or any source that isn't message-shaped. The
built-in behaviour is itself just a series of these processors, and yours slot in
by priority.

Higher priority runs first. The built-in pipeline, for reference — pick a priority
relative to these to slot yours in:

| Processor | Priority | Does |
|---|---:|---|
| `ConfigProcessor` | `1000` | merges the static `document` config into the model |
| `DiscoveryProcessor` | `500` | assembles channels/operations/messages from the providers |
| `PayloadProcessor` | `-100` | derives each message's payload schema |
| `CanonicalizeMessagesProcessor` | `-500` | hoists channel messages into `components.messages` |
| `VersionValidationProcessor` | `-1000` | rejects an unsupported `asyncapi` version (runs last) |

A processor left at the **default priority `0`** runs after discovery (the
channels/operations/messages exist, messages still inline) and before payload
derivation — a sensible default for refining the discovered document.

**For message-shaped sources specifically**, there's a narrower convenience seam:
implement `Zeusi\AsyncApiBundle\Discovery\MessageProviderInterface` to yield
messages from somewhere other than attributes (e.g. a registry, or routing
config), and the built-in discovery processor assembles the channels and
operations for you. Reach for this when your source really is a flat list of
messages; otherwise, write a processor.

## Where it pays off most

AsyncAPI shines as an event contract **between services**: a **shared broker**
(AMQP / Kafka / SQS / Pub-Sub) with heterogeneous consumers, where the JSON
payload is a public contract others depend on.

If your events stay inside a single app — **internal queues** (`doctrine://`,
`redis://`) with no external consumer — there's no cross-service contract to
publish. The documentation can still earn its keep, though: a schema catalog for
onboarding and for understanding the event flow.

## License

This project is licensed under the MIT License. See the [LICENSE](LICENSE) file for details.
