<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Components object: the catalog of reusable definitions.
 *
 * `messages` and `schemas` are the modelled catalogs; every other component type
 * (securitySchemes, bindings…) is kept verbatim in the local passthrough. This is
 * the one object where modelled and passthrough content can share a key, so
 * rendering deep-merges them — the modelled entries win on a leaf conflict, the
 * rest coexists.
 */
final class Components
{
    use HasPassthrough;

    /**
     * Reusable payload schemas, as raw JSON Schema.
     *
     * @var array<string, array<string, mixed>>
     */
    public array $schemas = [];

    /**
     * @var array<string, Message>
     */
    public array $messages = [];

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->schemas !== []) {
            $data['schemas'] = $this->schemas;
        }

        if ($this->messages !== []) {
            $data['messages'] = array_map(static fn(Message $message): array => $message->toArray(), $this->messages);
        }

        return array_replace_recursive($this->passthrough, $data);
    }
}
