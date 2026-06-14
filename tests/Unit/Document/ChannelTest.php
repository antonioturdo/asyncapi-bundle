<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\Channel;
use Zeusi\AsyncApiBundle\Document\ExternalDocumentation;
use Zeusi\AsyncApiBundle\Document\Tag;

final class ChannelTest extends TestCase
{
    public function testFromArrayHydratesKnownFieldsKeepsPassthroughAndIgnoresMessages(): void
    {
        $channel = Channel::fromArray([
            'address' => 'orders',
            'title' => 'Orders',
            'bindings' => ['amqp' => ['is' => 'routingKey']],
            // Owned by discovery — not hydrated from config.
            'messages' => ['OrderPlaced' => ['$ref' => '#/components/messages/OrderPlaced']],
        ]);

        self::assertSame('orders', $channel->address);
        self::assertSame('Orders', $channel->title);
        self::assertSame([], $channel->messages);
        self::assertSame(['amqp' => ['is' => 'routingKey']], $channel->passthrough['bindings']);

        self::assertSame([
            'address' => 'orders',
            'title' => 'Orders',
            'bindings' => ['amqp' => ['is' => 'routingKey']],
        ], $channel->toArray());
    }

    public function testItRendersTheCalmOptionalFields(): void
    {
        $channel = new Channel(
            address: 'orders',
            title: 'Orders',
            summary: 'Order lifecycle events',
            externalDocs: new ExternalDocumentation(url: 'https://docs.acme.example/orders'),
        );
        $channel->tags = [new Tag(name: 'orders')];

        self::assertSame([
            'address' => 'orders',
            'title' => 'Orders',
            'summary' => 'Order lifecycle events',
            'tags' => [['name' => 'orders']],
            'externalDocs' => ['url' => 'https://docs.acme.example/orders'],
        ], $channel->toArray());
    }
}
