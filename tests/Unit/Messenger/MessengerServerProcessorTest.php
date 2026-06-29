<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Messenger;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Channel;
use Zeusi\AsyncApiBundle\Document\Message;
use Zeusi\AsyncApiBundle\Document\Reference;
use Zeusi\AsyncApiBundle\Document\Server;
use Zeusi\AsyncApiBundle\Messenger\MessengerServerProcessor;
use Zeusi\AsyncApiBundle\Messenger\RouteResolver;
use Zeusi\AsyncApiBundle\Processor\DocumentContext;

final class MessengerServerProcessorTest extends TestCase
{
    public function testItAddsServersOnlyForTransportsADocumentedMessageIsRoutedTo(): void
    {
        $document = $this->documentWithMessage('OrderPlaced', \stdClass::class);

        (new MessengerServerProcessor(
            transports: [
                'events' => 'amqp://guest:guest@broker.acme:5672/%2f/messages',
                'unused' => 'redis://localhost:6379',
            ],
            routeResolver: new RouteResolver([
                \stdClass::class => ['events'],
            ]),
        ))->process($document, new DocumentContext('default'));

        // 'events' carries a documented message → server added (host:port + protocol).
        self::assertSame(
            ['host' => 'broker.acme:5672', 'protocol' => 'amqp'],
            ($document->servers['events'] ?? null)?->toArray(),
        );
        // 'unused' is a real broker transport, but no documented message routes there.
        self::assertArrayNotHasKey('unused', $document->servers);
        self::assertCount(1, $document->servers);
    }

    public function testItResolvesPrefixWildcardRoutesAndSkipsInternalTransports(): void
    {
        $document = $this->documentWithMessage('ServerThing', Server::class);

        (new MessengerServerProcessor(
            transports: ['internal' => 'doctrine://default'],
            routeResolver: new RouteResolver(['Zeusi\\AsyncApiBundle\\Document\\*' => ['internal']]),
        ))->process($document, new DocumentContext('default'));

        // The class matches the wildcard, but the transport is a non-broker → no server.
        self::assertSame([], $document->servers);
    }

    public function testItResolvesRoutesByImplementedInterface(): void
    {
        $document = $this->documentWithMessage('OrderPlaced', ConcreteRoutedEvent::class);

        (new MessengerServerProcessor(
            transports: ['events' => 'amqp://broker.acme:5672'],
            // Routed by the interface, not the concrete class — Messenger matches it.
            routeResolver: new RouteResolver([RoutedEvent::class => ['events']]),
        ))->process($document, new DocumentContext('default'));

        self::assertSame(
            ['host' => 'broker.acme:5672', 'protocol' => 'amqp'],
            ($document->servers['events'] ?? null)?->toArray(),
        );
    }

    public function testItFillsContentTypeFromTheRoutedTransportSerializer(): void
    {
        $document = $this->documentWithMessage('OrderPlaced', \stdClass::class);

        (new MessengerServerProcessor(
            transports: ['events' => 'amqp://broker.acme:5672'],
            routeResolver: new RouteResolver([\stdClass::class => ['events']]),
            contentTypes: ['events' => 'application/xml'],
        ))->process($document, new DocumentContext('default'));

        self::assertSame('application/xml', $this->message($document, 'OrderPlaced')->contentType);
    }

    public function testItDoesNotOverrideAContentTypeDeclaredOnTheMessage(): void
    {
        $document = $this->documentWithMessage('OrderPlaced', \stdClass::class);
        $this->message($document, 'OrderPlaced')->contentType = 'application/cloudevents+json';

        (new MessengerServerProcessor(
            transports: ['events' => 'amqp://broker.acme:5672'],
            routeResolver: new RouteResolver([\stdClass::class => ['events']]),
            contentTypes: ['events' => 'application/xml'],
        ))->process($document, new DocumentContext('default'));

        self::assertSame('application/cloudevents+json', $this->message($document, 'OrderPlaced')->contentType);
    }

    public function testItAssociatesEachChannelToItsServersWhenSeveralExist(): void
    {
        $document = new AsyncApiDocument();
        $document->channels['orders'] = $this->channelWith('OrderPlaced', \stdClass::class);
        $document->channels['audit'] = $this->channelWith('AuditWritten', ConcreteRoutedEvent::class);

        (new MessengerServerProcessor(
            transports: [
                'events' => 'amqp://broker.acme:5672',
                'logs' => 'kafka://broker.acme:9092',
            ],
            routeResolver: new RouteResolver([
                \stdClass::class => ['events'],
                ConcreteRoutedEvent::class => ['logs'],
            ]),
        ))->process($document, new DocumentContext('default'));

        self::assertCount(2, $document->servers);
        self::assertSame(['#/servers/events'], $this->channelServerRefs($document, 'orders'));
        self::assertSame(['#/servers/logs'], $this->channelServerRefs($document, 'audit'));
    }

    public function testItDoesNotAssociateServersWhenOnlyOneExists(): void
    {
        $document = $this->documentWithMessage('OrderPlaced', \stdClass::class);

        (new MessengerServerProcessor(
            transports: ['events' => 'amqp://broker.acme:5672'],
            routeResolver: new RouteResolver([\stdClass::class => ['events']]),
        ))->process($document, new DocumentContext('default'));

        // Single server: the channel→server link would be noise, so it is left empty.
        self::assertSame([], $document->channels['orders']->servers);
    }

    public function testItDoesNotOverrideAnAlreadyDeclaredServer(): void
    {
        $document = $this->documentWithMessage('OrderPlaced', \stdClass::class);
        $document->servers['events'] = new Server(host: 'configured.example:5672', protocol: 'amqp');

        (new MessengerServerProcessor(
            transports: ['events' => 'amqp://other.host:5672'],
            routeResolver: new RouteResolver([\stdClass::class => ['events']]),
        ))->process($document, new DocumentContext('default'));

        self::assertSame('configured.example:5672', $document->servers['events']->host);
    }

    /**
     * @param class-string $payloadClass
     */
    private function documentWithMessage(string $name, string $payloadClass): AsyncApiDocument
    {
        $document = new AsyncApiDocument();
        $channel = new Channel(address: 'orders');
        $message = new Message(name: $name);
        $message->payloadClass = $payloadClass;
        $channel->messages[$name] = $message;
        $document->channels['orders'] = $channel;

        return $document;
    }

    private function message(AsyncApiDocument $document, string $name): Message
    {
        $message = $document->channels['orders']->messages[$name];
        self::assertInstanceOf(Message::class, $message);

        return $message;
    }

    /**
     * @param class-string $payloadClass
     */
    private function channelWith(string $messageName, string $payloadClass): Channel
    {
        $channel = new Channel();
        $message = new Message(name: $messageName);
        $message->payloadClass = $payloadClass;
        $channel->messages[$messageName] = $message;

        return $channel;
    }

    /**
     * @return list<string>
     */
    private function channelServerRefs(AsyncApiDocument $document, string $key): array
    {
        return array_map(static fn(Reference $ref): string => $ref->ref, $document->channels[$key]->servers);
    }
}

interface RoutedEvent {}

final class ConcreteRoutedEvent implements RoutedEvent {}
