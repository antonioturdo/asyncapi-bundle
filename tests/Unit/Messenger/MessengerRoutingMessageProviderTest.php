<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Messenger;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;
use Zeusi\AsyncApiBundle\Discovery\DiscoveredMessage;
use Zeusi\AsyncApiBundle\Document\OperationAction;
use Zeusi\AsyncApiBundle\Messenger\MessengerRoutingMessageProvider;

final class MessengerRoutingMessageProviderTest extends TestCase
{
    public function testExactTierDiscoversConcreteRoutingKeysOnly(): void
    {
        $provider = new MessengerRoutingMessageProvider(
            routing: [
                ExactEvent::class => ['events'],
                RoutedInterface::class => ['events'], // a pattern, not a concrete class
                '*' => ['audit'],                     // catch-all: never a source
            ],
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
            routing: [
                AnnotatedEvent::class => ['events'],
                AbstractEvent::class => ['events'],
            ],
        );

        self::assertSame([], $this->collect($provider));
    }

    public function testPatternsTierDiscoversScannedClassesMatchedByAnInterface(): void
    {
        $provider = new MessengerRoutingMessageProvider(
            routing: [RoutedInterface::class => ['events']],
            patterns: true,
            scannedClasses: [ImplementingEvent::class, Unrelated::class],
        );

        $discovered = $this->collect($provider);

        self::assertCount(1, $discovered);
        self::assertSame(ImplementingEvent::class, $discovered[0]->messageClass);
    }

    public function testPatternsTierIgnoresClassesMatchedOnlyByTheCatchAll(): void
    {
        $provider = new MessengerRoutingMessageProvider(
            routing: ['*' => ['audit']],
            patterns: true,
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
