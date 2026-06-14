<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Info object: document-level metadata.
 *
 * `title` and `version` are the only fields the specification requires.
 */
final class Info
{
    use HasExtensions;

    /**
     * @var list<Tag>
     */
    public array $tags = [];

    public function __construct(
        public string $title,
        public string $version,
        public ?string $description = null,
        public ?string $termsOfService = null,
        public ?Contact $contact = null,
        public ?License $license = null,
        public ?ExternalDocumentation $externalDocs = null,
    ) {}

    /**
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $info = new self('', '');
        $info->applyArray($data);

        return $info;
    }

    /**
     * Merges a config fragment onto this Info: known fields present in $data
     * override the current ones (others are kept), unknown fields (Info is fully
     * modelled, so these are `x-` extensions) go to the extensions bag. Lets a
     * config fragment override a seeded default partially.
     *
     * @param array<array-key, mixed> $data
     */
    public function applyArray(array $data): void
    {
        $title = $data['title'] ?? null;
        if (\is_string($title)) {
            $this->title = $title;
        }

        $version = $data['version'] ?? null;
        if (\is_string($version)) {
            $this->version = $version;
        }

        $description = $data['description'] ?? null;
        if (\is_string($description)) {
            $this->description = $description;
        }

        $termsOfService = $data['termsOfService'] ?? null;
        if (\is_string($termsOfService)) {
            $this->termsOfService = $termsOfService;
        }

        $contact = $data['contact'] ?? null;
        if (\is_array($contact)) {
            $this->contact = Contact::fromArray($contact);
        }

        $license = $data['license'] ?? null;
        if (\is_array($license)) {
            $this->license = License::fromArray($license);
        }

        $externalDocs = $data['externalDocs'] ?? null;
        if (\is_array($externalDocs)) {
            $this->externalDocs = ExternalDocumentation::fromArray($externalDocs);
        }

        $tags = $data['tags'] ?? null;
        if (\is_array($tags)) {
            $this->tags = [];
            foreach ($tags as $tag) {
                if (\is_array($tag)) {
                    $this->tags[] = Tag::fromArray($tag);
                }
            }
        }

        $known = ['title', 'version', 'description', 'termsOfService', 'contact', 'license', 'externalDocs', 'tags'];

        foreach ($data as $key => $value) {
            if (\is_string($key) && !\in_array($key, $known, true)) {
                $this->extensions[$key] = $value;
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'title' => $this->title,
            'version' => $this->version,
        ];

        if ($this->description !== null) {
            $data['description'] = $this->description;
        }

        if ($this->termsOfService !== null) {
            $data['termsOfService'] = $this->termsOfService;
        }

        if ($this->contact !== null) {
            $data['contact'] = $this->contact->toArray();
        }

        if ($this->license !== null) {
            $data['license'] = $this->license->toArray();
        }

        if ($this->tags !== []) {
            $data['tags'] = array_map(static fn(Tag $tag): array => $tag->toArray(), $this->tags);
        }

        if ($this->externalDocs !== null) {
            $data['externalDocs'] = $this->externalDocs->toArray();
        }

        return $this->withExtensions($data);
    }
}
