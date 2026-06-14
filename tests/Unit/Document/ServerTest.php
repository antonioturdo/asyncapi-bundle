<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\ExternalDocumentation;
use Zeusi\AsyncApiBundle\Document\Info;
use Zeusi\AsyncApiBundle\Document\Server;
use Zeusi\AsyncApiBundle\Document\ServerVariable;
use Zeusi\AsyncApiBundle\Document\Tag;

final class ServerTest extends TestCase
{
    public function testItRendersTheRequiredFieldsAndOmitsUnsetOptionalOnes(): void
    {
        $server = new Server(host: 'broker.acme.com:5672', protocol: 'amqp');

        self::assertSame(['host' => 'broker.acme.com:5672', 'protocol' => 'amqp'], $server->toArray());
    }

    public function testFromArrayHydratesKnownFieldsAndKeepsTheRestAsPassthrough(): void
    {
        $server = Server::fromArray([
            'host' => 'broker.acme.com:5672',
            'protocol' => 'amqp',
            'description' => 'Prod',
            'bindings' => ['amqp' => ['exchange' => 'events']],
            'security' => [['$ref' => '#/components/securitySchemes/user']],
        ]);

        self::assertSame('broker.acme.com:5672', $server->host);
        self::assertSame('Prod', $server->description);
        self::assertSame(['amqp' => ['exchange' => 'events']], $server->passthrough['bindings']);

        // Round-trip: unmodelled fields render verbatim alongside the typed ones.
        self::assertSame([
            'host' => 'broker.acme.com:5672',
            'protocol' => 'amqp',
            'description' => 'Prod',
            'bindings' => ['amqp' => ['exchange' => 'events']],
            'security' => [['$ref' => '#/components/securitySchemes/user']],
        ], $server->toArray());
    }

    public function testItRendersTheCalmOptionalFields(): void
    {
        $server = new Server(
            host: '{env}.broker.acme.com:5672',
            protocol: 'amqp',
            summary: 'Primary broker',
            externalDocs: new ExternalDocumentation(url: 'https://docs.acme.example/broker'),
        );
        $variable = new ServerVariable(default: 'prod', description: 'Deployment environment');
        $variable->enum = ['prod', 'staging'];
        $server->variables = ['env' => $variable];
        $server->tags = [new Tag(name: 'internal')];

        self::assertSame([
            'host' => '{env}.broker.acme.com:5672',
            'protocol' => 'amqp',
            'summary' => 'Primary broker',
            'variables' => [
                'env' => ['enum' => ['prod', 'staging'], 'default' => 'prod', 'description' => 'Deployment environment'],
            ],
            'tags' => [['name' => 'internal']],
            'externalDocs' => ['url' => 'https://docs.acme.example/broker'],
        ], $server->toArray());
    }

    public function testDocumentRendersServersAfterInfo(): void
    {
        $document = new AsyncApiDocument();
        $document->info = new Info('Acme', '1.0.0');
        $document->servers['production'] = new Server(
            host: 'broker.acme.com:5672',
            protocol: 'amqp',
            description: 'Production broker',
        );

        self::assertSame([
            'asyncapi' => '3.1.0',
            'info' => ['title' => 'Acme', 'version' => '1.0.0'],
            'servers' => [
                'production' => [
                    'host' => 'broker.acme.com:5672',
                    'protocol' => 'amqp',
                    'description' => 'Production broker',
                ],
            ],
        ], $document->toArray());
    }
}
