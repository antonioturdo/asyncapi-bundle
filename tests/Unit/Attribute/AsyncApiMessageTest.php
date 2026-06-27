<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Attribute;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;
use Zeusi\AsyncApiBundle\Document\OperationAction;

final class AsyncApiMessageTest extends TestCase
{
    public function testItExposesDeclaredMetadataAndDefaults(): void
    {
        $message = new AsyncApiMessage(
            summary: 'A user signed up',
            tags: ['user', 'lifecycle'],
        );

        self::assertSame('A user signed up', $message->summary);
        self::assertSame(['user', 'lifecycle'], $message->tags);

        // Channel is optional and the placement defaults to a published (send) message.
        self::assertNull($message->channel);
        self::assertSame(OperationAction::Send, $message->action);
        self::assertNull($message->name);
        self::assertNull($message->title);
        self::assertNull($message->description);
        // Undeclared: resolves to application/json at render time (or via Messenger).
        self::assertNull($message->contentType);
    }

    public function testItAcceptsAReceivePlacement(): void
    {
        $message = new AsyncApiMessage(channel: 'audit', action: OperationAction::Receive);

        self::assertSame('audit', $message->channel);
        self::assertSame(OperationAction::Receive, $message->action);
    }

    public function testItIsReadableAsAClassAttribute(): void
    {
        $reflection = new \ReflectionClass(SampleMessage::class);
        $attributes = $reflection->getAttributes(AsyncApiMessage::class);

        self::assertCount(1, $attributes);

        $message = $attributes[0]->newInstance();
        self::assertSame('orders', $message->channel);
        self::assertSame('Order placed', $message->title);
    }
}

#[AsyncApiMessage(channel: 'orders', title: 'Order placed')]
final class SampleMessage
{
    public function __construct(public string $orderId) {}
}
