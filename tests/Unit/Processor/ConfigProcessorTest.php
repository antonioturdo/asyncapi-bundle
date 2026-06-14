<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Info;
use Zeusi\AsyncApiBundle\Document\Server;
use Zeusi\AsyncApiBundle\Processor\ConfigProcessor;
use Zeusi\AsyncApiBundle\Processor\DocumentContext;

final class ConfigProcessorTest extends TestCase
{
    public function testItHydratesRootScalarsIntoTypedFieldsAndMergesTheRestIntoBase(): void
    {
        $document = new AsyncApiDocument();

        (new ConfigProcessor([
            'asyncapi' => '3.0.0',
            'id' => 'urn:acme:events',
            'defaultContentType' => 'application/json',
            'info' => ['title' => 'Acme', 'version' => '1.0.0'],
        ]))->process($document, new DocumentContext('default'));

        // The modelled root scalars become typed fields.
        self::assertSame('3.0.0', $document->asyncapi);
        self::assertSame('urn:acme:events', $document->id);
        self::assertSame('application/json', $document->defaultContentType);

        // Everything provided is modelled, so nothing spills into the extensions bag.
        self::assertInstanceOf(Info::class, $document->info);
        self::assertSame('Acme', $document->info->title);
        self::assertSame([], $document->extensions);

        // The rendered document carries asyncapi/id/defaultContentType up front.
        self::assertSame([
            'asyncapi' => '3.0.0',
            'id' => 'urn:acme:events',
            'defaultContentType' => 'application/json',
            'info' => ['title' => 'Acme', 'version' => '1.0.0'],
        ], $document->toArray());
    }

    public function testItHydratesServersIntoTypedObjectsWithLocalPassthrough(): void
    {
        $document = new AsyncApiDocument();

        (new ConfigProcessor([
            'servers' => [
                'production' => ['host' => 'broker.acme.com:5672', 'protocol' => 'amqp', 'bindings' => ['amqp' => []]],
            ],
            'info' => ['title' => 'Acme', 'version' => '1.0.0'],
        ]))->process($document, new DocumentContext('default'));

        self::assertInstanceOf(Server::class, $document->servers['production'] ?? null);
        self::assertSame('amqp', $document->servers['production']->protocol);
        // Unmodelled server field is kept locally, not lost.
        self::assertSame(['amqp' => []], $document->servers['production']->passthrough['bindings']);

        // servers and info are modelled, so nothing spills into the extensions bag.
        self::assertSame([], $document->extensions);
    }
}
