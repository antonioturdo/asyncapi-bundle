<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Contact object: contact information for the documented API.
 *
 * All fields are optional.
 */
final class Contact
{
    use HasExtensions;

    public function __construct(
        public ?string $name = null,
        public ?string $url = null,
        public ?string $email = null,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $name = $data['name'] ?? null;
        $url = $data['url'] ?? null;
        $email = $data['email'] ?? null;

        return new self(
            name: \is_string($name) ? $name : null,
            url: \is_string($url) ? $url : null,
            email: \is_string($email) ? $email : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [];

        foreach (['name' => $this->name, 'url' => $this->url, 'email' => $this->email] as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        return $this->withExtensions($data);
    }
}
