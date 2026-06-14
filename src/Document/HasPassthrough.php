<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * Verbatim bag for standard AsyncAPI fields this object does not model yet.
 *
 * Unlike {@see HasExtensions} (for `x-` specification extensions, where the
 * prefix is enforced), keys here are rendered exactly as given — the local,
 * per-object equivalent of a passthrough, so hydrating a config fragment never
 * loses fields that have no typed home yet (e.g. bindings, security).
 */
trait HasPassthrough
{
    /**
     * @var array<string, mixed>
     */
    public array $passthrough = [];

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withPassthrough(array $data): array
    {
        return [...$data, ...$this->passthrough];
    }
}
