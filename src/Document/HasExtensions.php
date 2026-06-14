<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * Generic bag for AsyncAPI specification extensions (`x-` fields).
 *
 * Also holds any field not yet modelled, so a processor is never blocked by a
 * missing property.
 */
trait HasExtensions
{
    /**
     * @var array<string, mixed>
     */
    public array $extensions = [];

    public function setExtension(string $name, mixed $value): void
    {
        $this->extensions[str_starts_with($name, 'x-') ? $name : 'x-' . $name] = $value;
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function withExtensions(array $data): array
    {
        return [...$data, ...$this->extensions];
    }
}
