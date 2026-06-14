<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Operation object: what the application does on a channel.
 *
 * Roughly 1:1 with a (message, action) pair. References its channel and the
 * messages it carries via JSON references into the document.
 */
final class Operation
{
    use HasExtensions;
    use HasPassthrough;

    /**
     * References to the messages this operation carries (under the channel's
     * `messages`).
     *
     * @var list<Reference>
     */
    public array $messages = [];

    /**
     * @var list<Tag>
     */
    public array $tags = [];

    public function __construct(
        public OperationAction $action,
        public Reference $channel,
        public ?string $title = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?ExternalDocumentation $externalDocs = null,
    ) {}

    /**
     * Known fields become typed; anything else (e.g. security, bindings, reply)
     * is kept verbatim in the passthrough bag.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $actionValue = $data['action'] ?? null;
        $action = (\is_string($actionValue) ? OperationAction::tryFrom($actionValue) : null) ?? OperationAction::Send;

        $title = $data['title'] ?? null;
        $summary = $data['summary'] ?? null;
        $description = $data['description'] ?? null;
        $externalDocs = $data['externalDocs'] ?? null;
        $tags = $data['tags'] ?? null;
        $messages = $data['messages'] ?? null;

        $operation = new self(
            action: $action,
            channel: self::reference($data['channel'] ?? null) ?? Reference::to(''),
            title: \is_string($title) ? $title : null,
            summary: \is_string($summary) ? $summary : null,
            description: \is_string($description) ? $description : null,
            externalDocs: \is_array($externalDocs) ? ExternalDocumentation::fromArray($externalDocs) : null,
        );

        if (\is_array($tags)) {
            foreach ($tags as $tag) {
                if (\is_array($tag)) {
                    $operation->tags[] = Tag::fromArray($tag);
                }
            }
        }

        if (\is_array($messages)) {
            foreach ($messages as $reference) {
                $resolved = self::reference($reference);
                if ($resolved !== null) {
                    $operation->messages[] = $resolved;
                }
            }
        }

        $known = ['action', 'channel', 'title', 'summary', 'description', 'externalDocs', 'tags', 'messages'];

        foreach ($data as $key => $value) {
            if (\is_string($key) && !\in_array($key, $known, true)) {
                $operation->passthrough[$key] = $value;
            }
        }

        return $operation;
    }

    /**
     * Builds a Reference from a `{$ref: '…'}` shape.
     */
    private static function reference(mixed $value): ?Reference
    {
        if (!\is_array($value) || !isset($value['$ref']) || !\is_string($value['$ref'])) {
            return null;
        }

        return Reference::to($value['$ref']);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'action' => $this->action->value,
            'channel' => $this->channel->toArray(),
        ];

        foreach (['title' => $this->title, 'summary' => $this->summary, 'description' => $this->description] as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        if ($this->tags !== []) {
            $data['tags'] = array_map(static fn(Tag $tag): array => $tag->toArray(), $this->tags);
        }

        if ($this->externalDocs !== null) {
            $data['externalDocs'] = $this->externalDocs->toArray();
        }

        if ($this->messages !== []) {
            $data['messages'] = array_map(static fn(Reference $reference): array => $reference->toArray(), $this->messages);
        }

        return $this->withExtensions($this->withPassthrough($data));
    }
}
