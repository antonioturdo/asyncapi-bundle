<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Integration;

use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Discovery\DiscoveredMessage;
use Zeusi\AsyncApiBundle\Discovery\MessageProviderInterface;
use Zeusi\AsyncApiBundle\Document\OperationAction;
use Zeusi\AsyncApiBundle\Generator\AsyncApiGenerator;
use Zeusi\AsyncApiBundle\Processor\CanonicalizeMessagesProcessor;
use Zeusi\AsyncApiBundle\Processor\ConfigProcessor;
use Zeusi\AsyncApiBundle\Processor\DiscoveryProcessor;

/**
 * Safety net: whatever the model and processors assemble must be a structurally
 * valid AsyncAPI document, checked against the official JSON Schema — for every
 * supported version.
 */
final class AsyncApiSchemaValidationTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function supportedVersions(): iterable
    {
        yield '3.0.0' => ['3.0.0', __DIR__ . '/../Support/asyncapi-3.0.0-schema.json'];
        yield '3.1.0' => ['3.1.0', __DIR__ . '/../Support/asyncapi-3.1.0-schema.json'];
    }

    #[DataProvider('supportedVersions')]
    public function testAGeneratedDocumentValidatesAgainstTheAsyncApiSchema(string $version, string $schemaPath): void
    {
        $provider = new class implements MessageProviderInterface {
            public function provide(): iterable
            {
                yield new DiscoveredMessage(\stdClass::class, OperationAction::Send, 'orders', 'OrderPlaced', 'Order placed', 'An order was placed', null, 'application/json', ['order']);
                yield new DiscoveredMessage(\stdClass::class, OperationAction::Send, 'orders', 'OrderCancelled', 'Order cancelled', null, null, 'application/json', []);
                yield new DiscoveredMessage(\stdClass::class, OperationAction::Receive, null, 'AuditWritten', 'Audit written', null, null, 'application/json', []);
            }
        };

        $generator = new AsyncApiGenerator([
            new ConfigProcessor([
                'asyncapi' => $version,
                'info' => ['title' => 'Acme Events', 'version' => '1.0.0', 'description' => 'Events Acme publishes.'],
                'servers' => [
                    'production' => ['host' => 'broker.acme.com:5672', 'protocol' => 'amqp'],
                ],
            ]),
            new DiscoveryProcessor([$provider]),
            new CanonicalizeMessagesProcessor(),
        ]);

        $document = json_decode((string) json_encode($generator->generate('acme')->toArray()));
        $schema = json_decode((string) file_get_contents($schemaPath));

        $error = (new Validator())->validate($document, $schema)->error();

        self::assertNull(
            $error,
            $error === null ? '' : (string) json_encode((new ErrorFormatter())->format($error), JSON_PRETTY_PRINT),
        );
    }

    /**
     * Hydration safety net: an extensive static `document` (no DTOs to discover)
     * must round-trip through the typed model + local passthrough into a
     * spec-valid document — exercising the channels/operations/components paths.
     */
    #[DataProvider('supportedVersions')]
    public function testAnExtensiveStaticConfigDocumentValidatesAgainstTheAsyncApiSchema(string $version, string $schemaPath): void
    {
        $generator = new AsyncApiGenerator([
            new ConfigProcessor([
                'asyncapi' => $version,
                'id' => 'urn:acme:events',
                'defaultContentType' => 'application/json',
                'info' => [
                    'title' => 'Acme Events',
                    'version' => '2.0.0',
                    'description' => 'The Acme event catalog.',
                    'termsOfService' => 'https://acme.example/terms',
                    'contact' => ['name' => 'API Team', 'url' => 'https://acme.example', 'email' => 'api@acme.example'],
                    'license' => ['name' => 'Apache 2.0', 'url' => 'https://www.apache.org/licenses/LICENSE-2.0'],
                    'tags' => [['name' => 'orders', 'description' => 'Order events']],
                    'externalDocs' => ['url' => 'https://docs.acme.example', 'description' => 'Docs'],
                ],
                'servers' => [
                    'production' => [
                        'host' => '{env}.broker.acme.com:5672',
                        'protocol' => 'amqp',
                        'protocolVersion' => '0.9.1',
                        'pathname' => '/prod',
                        'title' => 'Production',
                        'summary' => 'Prod broker',
                        'description' => 'The production AMQP broker.',
                        'variables' => [
                            'env' => ['enum' => ['prod', 'staging'], 'default' => 'prod', 'description' => 'Environment'],
                        ],
                        'tags' => [['name' => 'internal']],
                        'externalDocs' => ['url' => 'https://docs.acme.example/broker'],
                        // unmodelled → server passthrough
                        'security' => [['$ref' => '#/components/securitySchemes/user']],
                    ],
                ],
                'channels' => [
                    'orders' => [
                        'address' => 'orders',
                        'title' => 'Orders',
                        'summary' => 'Order lifecycle',
                        'description' => 'Order lifecycle events.',
                        'tags' => [['name' => 'orders']],
                        'externalDocs' => ['url' => 'https://docs.acme.example/orders'],
                        // unmodelled → channel passthrough
                        'parameters' => [
                            'region' => ['description' => 'Region', 'default' => 'eu'],
                        ],
                    ],
                ],
                'operations' => [
                    'publishOrder' => [
                        'action' => 'send',
                        'channel' => ['$ref' => '#/channels/orders'],
                        'title' => 'Publish order',
                        'summary' => 'Publish an order event',
                        'description' => 'Publishes an order event.',
                        'tags' => [['name' => 'orders']],
                        'externalDocs' => ['url' => 'https://docs.acme.example/publish'],
                        // unmodelled → operation passthrough
                        'security' => [['$ref' => '#/components/securitySchemes/user']],
                    ],
                ],
                'components' => [
                    'schemas' => [
                        'Order' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]],
                    ],
                    'securitySchemes' => [
                        'user' => ['type' => 'userPassword', 'description' => 'User/password auth'],
                    ],
                ],
            ]),
            // No DiscoveryProcessor: the document is entirely static.
        ]);

        $document = json_decode((string) json_encode($generator->generate('acme')->toArray()));
        $schema = json_decode((string) file_get_contents($schemaPath));

        $error = (new Validator())->validate($document, $schema)->error();

        self::assertNull(
            $error,
            $error === null ? '' : (string) json_encode((new ErrorFormatter())->format($error), JSON_PRETTY_PRINT),
        );
    }
}
