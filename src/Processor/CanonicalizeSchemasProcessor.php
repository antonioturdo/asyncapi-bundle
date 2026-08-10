<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Processor;

use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Message;

/**
 * Hoists the definitions a payload references into `components.schemas`.
 *
 * An extracted payload is self-contained: the types it references sit in a local
 * `definitions`/`$defs` block, pointed at relative to that payload's own root. Once
 * the payload is embedded in the document those pointers no longer resolve, so each
 * definition is moved into the shared catalog and the pointers are rewritten to it.
 * A type used by several messages is then defined once.
 *
 * A definition is published under its short name, or under the name declared for
 * its class in `schemas.names`. Two different classes claiming the same name is an
 * error: the name is what consumers reference, so it is never picked for you.
 */
final class CanonicalizeSchemasProcessor implements AsyncApiProcessorInterface
{
    /**
     * The blocks an extracted schema keeps its definitions in, one per dialect.
     */
    private const DEFINITION_BLOCKS = ['definitions', '$defs'];

    /**
     * @param array<class-string, string> $names class => the name its schema is published under
     */
    public function __construct(
        private readonly array $names = [],
    ) {}

    public function process(AsyncApiDocument $document, DocumentContext $context, GenerationState $state): void
    {
        /** @var array<string, class-string> $claimedBy published name => the class holding it */
        $claimedBy = [];

        foreach ($this->locatedMessages($document) as [$message, $payloadPointer]) {
            $payload = $message->payload;

            if ($payload === null) {
                continue;
            }

            $publishedNames = $this->publishedNames($state->payloadRefs($message), $claimedBy);

            $rewrites = [];

            foreach ($publishedNames as $pointer => $name) {
                $rewrites[$pointer] = '#/components/schemas/' . $name;
            }

            // A recursive type refers to the payload root as `#`, which now resolves
            // against the document root instead.
            $rewrites['#'] = $payloadPointer;

            // Rewrite before moving, so a definition referencing another one is
            // hoisted with its pointers already corrected.
            $payload = $this->rewriteRefs($payload, $rewrites);

            foreach ($publishedNames as $pointer => $name) {
                $definition = $this->pluck($payload, $pointer);

                if ($definition !== null && !isset($document->components->schemas[$name])) {
                    $document->components->schemas[$name] = $definition;
                }
            }

            $message->payload = $this->removeEmptyDefinitionBlocks($payload);
        }
    }

    /**
     * Resolves the name each referenced definition is published under, rejecting a
     * name two different classes would claim.
     *
     * For instance `['#/definitions/Engine' => 'Engine']`, or the name declared for
     * the class when `schemas.names` has one.
     *
     * @param array<string, class-string> $refs      pointer => the class it denotes
     * @param array<string, class-string> $claimedBy carried across messages, by reference
     * @return array<string, string>                 pointer => published name
     */
    private function publishedNames(array $refs, array &$claimedBy): array
    {
        $names = [];

        foreach ($refs as $pointer => $class) {
            $name = $this->names[$class] ?? $this->definitionName($pointer);

            if (($claimedBy[$name] ?? $class) !== $class) {
                throw new \LogicException(\sprintf(
                    'The AsyncAPI schema name "%s" is claimed by both %s and %s. Declare a name for one of them under asyncapi.schemas.names.',
                    $name,
                    $claimedBy[$name],
                    $class,
                ));
            }

            $claimedBy[$name] = $class;
            $names[$pointer] = $name;
        }

        return $names;
    }

    /**
     * Every message carrying a payload, with the pointer that payload sits at.
     *
     * For instance `[$car, '#/components/messages/Car/payload']`, or
     * `[$car, '#/channels/garage/messages/Car/payload']` for a message left inline.
     *
     * @return list<array{Message, string}>
     */
    private function locatedMessages(AsyncApiDocument $document): array
    {
        $located = [];

        foreach ($document->components->messages as $key => $message) {
            $located[] = [$message, '#/components/messages/' . $key . '/payload'];
        }

        // Messages left inline, when the canonicalization of messages is not in use.
        foreach ($document->channels as $channelKey => $channel) {
            foreach ($channel->messages as $messageKey => $message) {
                if ($message instanceof Message) {
                    $located[] = [$message, '#/channels/' . $channelKey . '/messages/' . $messageKey . '/payload'];
                }
            }
        }

        return $located;
    }

    /**
     * Replaces every `$ref` the map covers, at any depth.
     *
     * @param array<string, mixed>  $node
     * @param array<string, string> $rewrites pointer => its replacement, for instance
     *        `['#/definitions/Engine' => '#/components/schemas/Engine']`
     * @return array<string, mixed>
     */
    private function rewriteRefs(array $node, array $rewrites): array
    {
        foreach ($node as $key => $value) {
            if ($key === '$ref' && \is_string($value) && isset($rewrites[$value])) {
                $node[$key] = $rewrites[$value];
            } elseif (\is_array($value)) {
                $node[$key] = $this->rewriteRefs($value, $rewrites);
            }
        }

        return $node;
    }

    /**
     * Removes the value a pointer addresses and returns it, or null when the pointer
     * addresses nothing.
     *
     * Given `#/definitions/Engine`, returns the Engine schema and leaves the payload
     * without it.
     *
     * @param array<string, mixed> $payload
     * @return array<string, mixed>|null
     */
    private function pluck(array &$payload, string $pointer): ?array
    {
        $segments = $this->segments($pointer);

        if ($segments === []) {
            return null;
        }

        $leaf = array_pop($segments);
        $node = &$payload;

        foreach ($segments as $segment) {
            if (!\is_array($node) || !\array_key_exists($segment, $node)) {
                return null;
            }

            $node = &$node[$segment];
        }

        if (!\is_array($node) || !\array_key_exists($leaf, $node)) {
            return null;
        }

        $definition = $node[$leaf];
        unset($node[$leaf]);

        return \is_array($definition) ? $definition : null;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function removeEmptyDefinitionBlocks(array $payload): array
    {
        foreach (self::DEFINITION_BLOCKS as $block) {
            if (($payload[$block] ?? null) === []) {
                unset($payload[$block]);
            }
        }

        return $payload;
    }

    /**
     * The name a definition carries in the payload it came from: the last segment of
     * its pointer.
     */
    private function definitionName(string $pointer): string
    {
        $segments = $this->segments($pointer);

        return $segments === [] ? $pointer : (string) end($segments);
    }

    /**
     * @return list<string>
     */
    private function segments(string $pointer): array
    {
        if (!str_starts_with($pointer, '#/')) {
            return [];
        }

        return array_map(
            static fn(string $segment): string => str_replace(['~1', '~0'], ['/', '~'], $segment),
            explode('/', substr($pointer, 2)),
        );
    }
}
