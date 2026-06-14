<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI Server object: a broker/endpoint where messages flow.
 *
 * `host` and `protocol` are the only fields the specification requires.
 */
final class Server
{
    use HasExtensions;
    use HasPassthrough;

    /**
     * @var array<string, ServerVariable>
     */
    public array $variables = [];

    /**
     * @var list<Tag>
     */
    public array $tags = [];

    public function __construct(
        public string $host,
        public string $protocol,
        public ?string $pathname = null,
        public ?string $protocolVersion = null,
        public ?string $title = null,
        public ?string $summary = null,
        public ?string $description = null,
        public ?ExternalDocumentation $externalDocs = null,
    ) {}

    /**
     * Known fields become typed; anything else (e.g. bindings, security) is kept
     * verbatim in the passthrough bag.
     *
     * @param array<array-key, mixed> $data
     */
    public static function fromArray(array $data): self
    {
        $host = $data['host'] ?? null;
        $protocol = $data['protocol'] ?? null;
        $pathname = $data['pathname'] ?? null;
        $protocolVersion = $data['protocolVersion'] ?? null;
        $title = $data['title'] ?? null;
        $summary = $data['summary'] ?? null;
        $description = $data['description'] ?? null;
        $externalDocs = $data['externalDocs'] ?? null;
        $variables = $data['variables'] ?? null;
        $tags = $data['tags'] ?? null;

        $server = new self(
            host: \is_string($host) ? $host : '',
            protocol: \is_string($protocol) ? $protocol : '',
            pathname: \is_string($pathname) ? $pathname : null,
            protocolVersion: \is_string($protocolVersion) ? $protocolVersion : null,
            title: \is_string($title) ? $title : null,
            summary: \is_string($summary) ? $summary : null,
            description: \is_string($description) ? $description : null,
            externalDocs: \is_array($externalDocs) ? ExternalDocumentation::fromArray($externalDocs) : null,
        );

        if (\is_array($variables)) {
            foreach ($variables as $key => $variable) {
                if (\is_string($key) && \is_array($variable)) {
                    $server->variables[$key] = ServerVariable::fromArray($variable);
                }
            }
        }

        if (\is_array($tags)) {
            foreach ($tags as $tag) {
                if (\is_array($tag)) {
                    $server->tags[] = Tag::fromArray($tag);
                }
            }
        }

        $known = ['host', 'protocol', 'pathname', 'protocolVersion', 'title', 'summary', 'description', 'externalDocs', 'variables', 'tags'];

        foreach ($data as $key => $value) {
            if (\is_string($key) && !\in_array($key, $known, true)) {
                $server->passthrough[$key] = $value;
            }
        }

        return $server;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'host' => $this->host,
            'protocol' => $this->protocol,
        ];

        foreach (['protocolVersion' => $this->protocolVersion, 'pathname' => $this->pathname, 'title' => $this->title, 'summary' => $this->summary, 'description' => $this->description] as $key => $value) {
            if ($value !== null) {
                $data[$key] = $value;
            }
        }

        if ($this->variables !== []) {
            $data['variables'] = array_map(static fn(ServerVariable $variable): array => $variable->toArray(), $this->variables);
        }

        if ($this->tags !== []) {
            $data['tags'] = array_map(static fn(Tag $tag): array => $tag->toArray(), $this->tags);
        }

        if ($this->externalDocs !== null) {
            $data['externalDocs'] = $this->externalDocs->toArray();
        }

        return $this->withExtensions($this->withPassthrough($data));
    }
}
