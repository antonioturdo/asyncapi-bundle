<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Integration;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Discovery\DiscoveredMessage;
use Zeusi\AsyncApiBundle\Discovery\MessageProviderInterface;
use Zeusi\AsyncApiBundle\Document\OperationAction;
use Zeusi\AsyncApiBundle\Generator\AsyncApiGenerator;
use Zeusi\AsyncApiBundle\Processor\CanonicalizeMessagesProcessor;
use Zeusi\AsyncApiBundle\Processor\CanonicalizeSchemasProcessor;
use Zeusi\AsyncApiBundle\Processor\ConfigProcessor;
use Zeusi\AsyncApiBundle\Processor\DiscoveryProcessor;
use Zeusi\AsyncApiBundle\Processor\PayloadProcessor;
use Zeusi\AsyncApiBundle\Tests\Fixtures\Payload\Car;
use Zeusi\JsonSchemaExtractor\Discoverer\ReflectionDiscoverer;
use Zeusi\JsonSchemaExtractor\Mapper\StandardJsonSchemaMapper;
use Zeusi\JsonSchemaExtractor\SchemaExtractor;
use Zeusi\JsonSchemaExtractor\Serialization\JsonEncodeSerializationStrategy;

/**
 * Reproduces issue #7: a message with a nested DTO (Car -> Engine) gets a payload
 * whose $ref points to a local definition. Once that payload is embedded into the
 * document, every $ref must still resolve, or the document is invalid.
 *
 * Uses the real extractor and the assembly processors directly (no kernel).
 */
final class NestedPayloadReferenceTest extends TestCase
{
    public function testNestedDtoPayloadReferencesResolveWithinTheDocument(): void
    {
        $extractor = new SchemaExtractor(
            discoverer: new ReflectionDiscoverer(),
            enrichers: [],
            serializationStrategy: new JsonEncodeSerializationStrategy(),
            mapper: new StandardJsonSchemaMapper(),
        );

        $provider = new class implements MessageProviderInterface {
            public function provide(): iterable
            {
                yield new DiscoveredMessage(
                    Car::class,
                    OperationAction::Send,
                    'garage',
                    'Car',
                    null,
                    'A car was assembled',
                    null,
                    'application/json',
                    [],
                );
            }
        };

        $generator = new AsyncApiGenerator([
            new ConfigProcessor(['asyncapi' => '3.1.0', 'info' => ['title' => 'Garage', 'version' => '1.0.0']]),
            new DiscoveryProcessor([$provider]),
            new PayloadProcessor($extractor),
            new CanonicalizeMessagesProcessor(),
            new CanonicalizeSchemasProcessor(),
        ]);

        $document = json_decode((string) json_encode($generator->generate('default')->toArray()), true);
        self::assertIsArray($document);

        foreach ($this->collectRefs($document) as $ref) {
            self::assertTrue(
                $this->pointerResolves($document, $ref),
                \sprintf('Dangling $ref in the generated document: %s', $ref),
            );
        }
    }

    /**
     * @return list<string>
     */
    private function collectRefs(mixed $node): array
    {
        if (!\is_array($node)) {
            return [];
        }

        $refs = [];

        foreach ($node as $key => $value) {
            if ($key === '$ref' && \is_string($value)) {
                $refs[] = $value;
            } else {
                $refs = array_merge($refs, $this->collectRefs($value));
            }
        }

        return $refs;
    }

    /**
     * @param array<array-key, mixed> $document
     */
    private function pointerResolves(array $document, string $ref): bool
    {
        if ($ref === '#') {
            return true;
        }

        if (!str_starts_with($ref, '#/')) {
            return false;
        }

        $node = $document;

        foreach (explode('/', substr($ref, 2)) as $segment) {
            $segment = str_replace(['~1', '~0'], ['/', '~'], $segment);

            if (!\is_array($node) || !\array_key_exists($segment, $node)) {
                return false;
            }

            $node = $node[$segment];
        }

        return true;
    }
}
