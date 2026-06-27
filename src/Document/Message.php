<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Message object: the data flowing through a channel.
 *
 * The `payload` carries the message's JSON Schema, or null when not set.
 */
final class Message
{
    use HasExtensions;

    /**
     * @var list<Tag>
     */
    public array $tags = [];

    /**
     * @var array<string, mixed>|null
     */
    public ?array $payload = null;

    /**
     * Source class the payload schema is derived from. Internal to processing —
     * not part of the AsyncAPI output.
     *
     * @var class-string|null
     */
    public ?string $payloadClass = null;

    public function __construct(
        public ?string $name = null,
        public ?string $title = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?string $contentType = null,
        public ?CorrelationId $correlationId = null,
        public ?ExternalDocumentation $externalDocs = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach (['name' => $this->name, 'title' => $this->title, 'summary' => $this->summary, 'description' => $this->description] as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        // contentType is optional (null = undeclared); the document falls back to the default.
        $data['contentType'] = $this->contentType ?? 'application/json';

        if ($this->payload !== null) {
            $data['payload'] = $this->payload;
        }

        if ($this->correlationId !== null) {
            $data['correlationId'] = $this->correlationId->toArray();
        }

        if ($this->tags !== []) {
            $data['tags'] = array_map(static fn(Tag $tag): array => $tag->toArray(), $this->tags);
        }

        if ($this->externalDocs !== null) {
            $data['externalDocs'] = $this->externalDocs->toArray();
        }

        return $this->withExtensions($data);
    }
}
