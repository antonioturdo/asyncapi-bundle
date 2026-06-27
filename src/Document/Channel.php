<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Channel object: the addressable medium where messages flow.
 */
final class Channel
{
    use HasExtensions;
    use HasPassthrough;

    /**
     * @var array<string, Message|Reference>
     */
    public array $messages = [];

    /**
     * Servers this channel is available on; empty means all of them.
     *
     * @var list<Reference>
     */
    public array $servers = [];

    /**
     * @var list<Tag>
     */
    public array $tags = [];

    public function __construct(
        public ?string $address = null,
        public ?string $title = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?ExternalDocumentation $externalDocs = null,
    ) {}

    /**
     * Known fields become typed; anything else (e.g. bindings, parameters) is
     * kept verbatim in the passthrough bag. `messages` are not hydrated here.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $address = $data['address'] ?? null;
        $title = $data['title'] ?? null;
        $summary = $data['summary'] ?? null;
        $description = $data['description'] ?? null;
        $externalDocs = $data['externalDocs'] ?? null;
        $tags = $data['tags'] ?? null;

        $channel = new self(
            address: \is_string($address) ? $address : null,
            title: \is_string($title) ? $title : null,
            summary: \is_string($summary) ? $summary : null,
            description: \is_string($description) ? $description : null,
            externalDocs: \is_array($externalDocs) ? ExternalDocumentation::fromArray($externalDocs) : null,
        );

        if (\is_array($tags)) {
            foreach ($tags as $tag) {
                if (\is_array($tag)) {
                    $channel->tags[] = Tag::fromArray($tag);
                }
            }
        }

        $servers = $data['servers'] ?? null;

        if (\is_array($servers)) {
            foreach ($servers as $server) {
                if (\is_array($server) && \is_string($server['$ref'] ?? null)) {
                    $channel->servers[] = Reference::to($server['$ref']);
                }
            }
        }

        $known = ['address', 'title', 'summary', 'description', 'externalDocs', 'tags', 'messages', 'servers'];

        foreach ($data as $key => $value) {
            if (\is_string($key) && !\in_array($key, $known, true)) {
                $channel->passthrough[$key] = $value;
            }
        }

        return $channel;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach (['address' => $this->address, 'title' => $this->title, 'summary' => $this->summary, 'description' => $this->description] as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        if ($this->servers !== []) {
            $data['servers'] = array_map(static fn(Reference $server): array => $server->toArray(), $this->servers);
        }

        // Each entry is either an inline Message or a Reference, rendered as-is.
        if ($this->messages !== []) {
            $data['messages'] = array_map(
                static fn(Message|Reference $message): array => $message->toArray(),
                $this->messages,
            );
        }

        if ($this->tags !== []) {
            $data['tags'] = array_map(static fn(Tag $tag): array => $tag->toArray(), $this->tags);
        }

        if ($this->externalDocs !== null) {
            $data['externalDocs'] = $this->externalDocs->toArray();
        }

        return $this->withExtensions($this->withPassthrough($data));
    }
}
