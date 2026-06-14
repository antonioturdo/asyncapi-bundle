<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * Typed, mutable in-memory model of an AsyncAPI 3.x document.
 *
 * Every standard root field is modelled as a typed object (info, servers,
 * channels, operations, components), each of which keeps its own unmodelled
 * fields in a local passthrough bag. Document-level `x-` extensions live in the
 * extensions bag.
 */
final class AsyncApiDocument
{
    use HasExtensions;

    public string $asyncapi;

    public ?string $id = null;

    public ?string $defaultContentType = null;

    /**
     * The document's Info object (title and version are required by the spec).
     */
    public ?Info $info = null;

    /**
     * @var array<string, Server>
     */
    public array $servers = [];

    /**
     * @var array<string, Channel>
     */
    public array $channels = [];

    /**
     * @var array<string, Operation>
     */
    public array $operations = [];

    public Components $components;

    public function __construct()
    {
        $this->asyncapi = AsyncApiVersion::default()->value;
        $this->components = new Components();
    }

    /**
     * Merges an AsyncAPI document fragment onto this document: modelled fields are
     * hydrated into their typed objects (info merges onto the existing one),
     * unmodelled component types go to the components passthrough, and any
     * remaining top-level key (an `x-` extension) goes to the extensions bag.
     *
     * @param array<array-key, mixed> $fragment
     */
    public function applyArray(array $fragment): void
    {
        if (isset($fragment['asyncapi']) && \is_string($fragment['asyncapi'])) {
            $this->asyncapi = $fragment['asyncapi'];
        }

        if (isset($fragment['id']) && \is_string($fragment['id'])) {
            $this->id = $fragment['id'];
        }

        if (isset($fragment['defaultContentType']) && \is_string($fragment['defaultContentType'])) {
            $this->defaultContentType = $fragment['defaultContentType'];
        }

        if (isset($fragment['info']) && \is_array($fragment['info'])) {
            if ($this->info === null) {
                $this->info = Info::fromArray($fragment['info']);
            } else {
                $this->info->applyArray($fragment['info']);
            }
        }

        if (isset($fragment['servers']) && \is_array($fragment['servers'])) {
            foreach ($fragment['servers'] as $name => $server) {
                if (\is_string($name) && \is_array($server)) {
                    $this->servers[$name] = Server::fromArray($server);
                }
            }
        }

        if (isset($fragment['channels']) && \is_array($fragment['channels'])) {
            foreach ($fragment['channels'] as $name => $channel) {
                if (\is_string($name) && \is_array($channel)) {
                    $this->channels[$name] = Channel::fromArray($channel);
                }
            }
        }

        if (isset($fragment['operations']) && \is_array($fragment['operations'])) {
            foreach ($fragment['operations'] as $name => $operation) {
                if (\is_string($name) && \is_array($operation)) {
                    $this->operations[$name] = Operation::fromArray($operation);
                }
            }
        }

        if (isset($fragment['components']) && \is_array($fragment['components'])) {
            foreach ($fragment['components'] as $key => $value) {
                if (\is_string($key)) {
                    $this->components->passthrough[$key] = $value;
                }
            }
        }

        unset(
            $fragment['asyncapi'],
            $fragment['id'],
            $fragment['defaultContentType'],
            $fragment['info'],
            $fragment['servers'],
            $fragment['channels'],
            $fragment['operations'],
            $fragment['components'],
        );

        foreach ($fragment as $key => $value) {
            if (\is_string($key)) {
                $this->extensions[$key] = $value;
            }
        }
    }

    /**
     * @return array<array-key, mixed>
     */
    public function toArray(): array
    {
        $data = ['asyncapi' => $this->asyncapi];

        if ($this->id !== null) {
            $data['id'] = $this->id;
        }

        if ($this->defaultContentType !== null) {
            $data['defaultContentType'] = $this->defaultContentType;
        }

        if ($this->info !== null) {
            $data['info'] = $this->info->toArray();
        }

        if ($this->servers !== []) {
            $data['servers'] = array_map(static fn(Server $server): array => $server->toArray(), $this->servers);
        }

        if ($this->channels !== []) {
            $data['channels'] = array_map(static fn(Channel $channel): array => $channel->toArray(), $this->channels);
        }

        if ($this->operations !== []) {
            $data['operations'] = array_map(static fn(Operation $operation): array => $operation->toArray(), $this->operations);
        }

        $components = $this->components->toArray();

        if ($components !== []) {
            $data['components'] = $components;
        }

        return $this->withExtensions($data);
    }
}
