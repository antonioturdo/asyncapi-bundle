<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Reference object: a `$ref` pointer to a definition elsewhere in the
 * document (or an external one).
 */
final class Reference
{
    public function __construct(
        public string $ref,
    ) {}

    public static function to(string $ref): self
    {
        return new self($ref);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['$ref' => $this->ref];
    }
}
