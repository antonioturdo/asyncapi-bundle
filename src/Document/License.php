<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI License object: license information for the documented API.
 *
 * `name` is the only field the specification requires.
 */
final class License
{
    use HasExtensions;

    public function __construct(
        public string $name,
        public ?string $identifier = null,
        public ?string $url = null,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $name = $data['name'] ?? null;
        $identifier = $data['identifier'] ?? null;
        $url = $data['url'] ?? null;

        return new self(
            name: \is_string($name) ? $name : '',
            identifier: \is_string($identifier) ? $identifier : null,
            url: \is_string($url) ? $url : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = ['name' => $this->name];

        foreach (['identifier' => $this->identifier, 'url' => $this->url] as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $this->withExtensions($data);
    }
}
