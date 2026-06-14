<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Server Variable object: a substitution variable for a server's fields.
 *
 * All fields are optional.
 */
final class ServerVariable
{
    use HasExtensions;

    /**
     * @var list<string>
     */
    public array $enum = [];

    /**
     * @var list<string>
     */
    public array $examples = [];

    public function __construct(
        public ?string $default = null,
        public ?string $description = null,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $default = $data['default'] ?? null;
        $description = $data['description'] ?? null;
        $enum = $data['enum'] ?? null;
        $examples = $data['examples'] ?? null;

        $variable = new self(
            default: \is_string($default) ? $default : null,
            description: \is_string($description) ? $description : null,
        );

        if (\is_array($enum)) {
            $variable->enum = array_values(array_filter($enum, 'is_string'));
        }

        if (\is_array($examples)) {
            $variable->examples = array_values(array_filter($examples, 'is_string'));
        }

        return $variable;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        if ($this->enum !== []) {
            $data['enum'] = $this->enum;
        }

        if ($this->default !== null) {
            $data['default'] = $this->default;
        }

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->examples !== []) {
            $data['examples'] = $this->examples;
        }

        return $this->withExtensions($data);
    }
}
