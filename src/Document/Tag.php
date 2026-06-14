<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Tag object: a named tag used to group other objects.
 *
 * `name` is the only field the specification requires.
 */
final class Tag
{
    use HasExtensions;

    public function __construct(
        public string $name,
        public ?string $description = null,
        public ?ExternalDocumentation $externalDocs = null,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $name = $data['name'] ?? null;
        $description = $data['description'] ?? null;
        $externalDocs = $data['externalDocs'] ?? null;

        return new self(
            name: \is_string($name) ? $name : '',
            description: \is_string($description) ? $description : null,
            externalDocs: \is_array($externalDocs) ? ExternalDocumentation::fromArray($externalDocs) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['name' => $this->name];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->externalDocs !== null) {
            $data['externalDocs'] = $this->externalDocs->toArray();
        }

        return $this->withExtensions($data);
    }
}
