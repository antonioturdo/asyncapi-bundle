<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI External Documentation object: a link to external documentation.
 *
 * `url` is the only field the specification requires.
 */
final class ExternalDocumentation
{
    use HasExtensions;

    public function __construct(
        public string $url,
        public ?string $description = null,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $url = $data['url'] ?? null;
        $description = $data['description'] ?? null;

        return new self(
            url: \is_string($url) ? $url : '',
            description: \is_string($description) ? $description : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['url' => $this->url];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        return $this->withExtensions($data);
    }
}
