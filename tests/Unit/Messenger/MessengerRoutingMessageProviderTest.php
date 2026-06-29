<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Messenger;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;
use Zeusi\AsyncApiBundle\Discovery\DiscoveredMessage;
use Zeusi\AsyncApiBundle\Document\OperationAction;
use Zeusi\AsyncApiBundle\Messenger\MessengerRoutingMessageProvider;
use Zeusi\AsyncApiBundle\Messenger\RouteResolver;

final class MessengerRoutingMessageProviderTest extends TestCase
{
    public function testItDiscoversScannedClassesRoutedByAnExactFqcn(): void
    {
        $provider = new MessengerRoutingMessageProvider(
            routeResolver: new RouteResolver([
                ExactEvent::class => ['events'],
                '*' => ['audit'], // catch-all: never a source
            ]),
            scannedClasses: [ExactEvent::class, Unrelated::class],
        );

        $discovered = $this->collect($provider);

        self::assertCount(1, $discovered);
        $message = $discovered[0];
        self::assertSame(ExactEvent::class, $message->messageClass);
        self::assertSame(OperationAction::Send, $message->action);
        self::assertSame('ExactEvent', $message->name);
        self::assertNull($message->channel);
        self::assertNull($message->contentType);
    }

    public function testItLeavesAnnotatedAndAbstractClassesAlone(): void
    {
        $provider = new MessengerRoutingMessageProvider(
            routeResolver: new RouteResolver([
                AnnotatedEvent::class => ['events'],
                AbstractEvent::class => ['events'],
            ]),
            scannedClasses: [AnnotatedEvent::class, AbstractEvent::class],
        );

        // Both are routed and scanned (so they match), but discovery skips them:
        // AnnotatedEvent carries #[AsyncApiMessage] (owned by the attribute), and
        // AbstractEvent is abstract (not a dispatchable message).
        self::assertSame([], $this->collect($provider));
    }

    public function testItDiscoversClassesMatchedByAnImplementedInterface(): void
    {
        $provider = new MessengerRoutingMessageProvider(
            routeResolver: new RouteResolver([RoutedInterface::class => ['events']]),
            scannedClasses: [ImplementingEvent::class, Unrelated::class],
        );

        $discovered = $this->collect($provider);

        self::assertCount(1, $discovered);
        self::assertSame(ImplementingEvent::class, $discovered[0]->messageClass);
    }

    public function testItIgnoresClassesMatchedOnlyByTheCatchAll(): void
    {
        $provider = new MessengerRoutingMessageProvider(
            routeResolver: new RouteResolver(['*' => ['audit']]),
            scannedClasses: [Unrelated::class],
        );

        self::assertSame([], $this->collect($provider));
    }

    /**
     * @return list<DiscoveredMessage>
     */
    private function collect(MessengerRoutingMessageProvider $provider): array
    {
        $messages = [];

        foreach ($provider->provide() as $message) {
            $messages[] = $message;
        }

        return $messages;
    }
}

interface RoutedInterface {}

final class ExactEvent {}

final class ImplementingEvent implements RoutedInterface {}

final class Unrelated {}

abstract class AbstractEvent {}

#[AsyncApiMessage]
final class AnnotatedEvent {}
