<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Components object: the catalog of reusable definitions.
 *
 * `messages` is the only modelled catalog; every other component type (schemas,
 * securitySchemes, bindings…) is kept verbatim in the local passthrough. This is
 * the one object where modelled and passthrough content can share a key
 * (`messages`), so rendering deep-merges them — the typed messages win on a leaf
 * conflict, the rest coexists.
 */
final class Components
{
    use HasPassthrough;

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

        if ($this->messages !== []) {
            $data['messages'] = array_map(static fn(Message $message): array => $message->toArray(), $this->messages);
        }

        return array_replace_recursive($this->passthrough, $data);
    }
}
