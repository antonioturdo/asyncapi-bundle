<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Channel;
use Zeusi\AsyncApiBundle\Document\Info;
use Zeusi\AsyncApiBundle\Document\Message;
use Zeusi\AsyncApiBundle\Document\Operation;
use Zeusi\AsyncApiBundle\Document\OperationAction;
use Zeusi\AsyncApiBundle\Document\Reference;
use Zeusi\AsyncApiBundle\Document\Tag;

final class AsyncApiDocumentTest extends TestCase
{
    public function testEmptyDocumentRendersOnlyTheRequiredRootFields(): void
    {
        $document = new AsyncApiDocument();
        $document->info = new Info('Acme Events', '1.0.0');

        self::assertSame([
            'asyncapi' => '3.1.0',
            'info' => ['title' => 'Acme Events', 'version' => '1.0.0'],
        ], $document->toArray());
    }

    public function testItRendersChannelsOperationsMessagesAndExtensions(): void
    {
        // The canonical form, as the CanonicalizeMessagesProcessor leaves it: the
        // message body lives in components, the channel holds a reference to it.
        $document = new AsyncApiDocument();
        $document->info = new Info('Acme Events', '2.1.0', 'Acme broker events');

        $message = new Message(name: 'OrderPlaced', summary: 'An order was placed');
        $message->tags = [new Tag(name: 'order')];
        $message->payload = ['type' => 'object'];
        $document->components->messages['OrderPlaced'] = $message;

        $channel = new Channel(address: 'order.placed');
        $channel->messages['OrderPlaced'] = Reference::to('#/components/messages/OrderPlaced');
        $document->channels['orders'] = $channel;

        $operation = new Operation(OperationAction::Send, Reference::to('#/channels/orders'), summary: 'Publish an order');
        $operation->messages = [Reference::to('#/channels/orders/messages/OrderPlaced')];
        $document->operations['sendOrderPlaced'] = $operation;

        $document->setExtension('audience', 'public');

        self::assertSame([
            'asyncapi' => '3.1.0',
            'info' => ['title' => 'Acme Events', 'version' => '2.1.0', 'description' => 'Acme broker events'],
            'channels' => [
                'orders' => [
                    'address' => 'order.placed',
                    'messages' => [
                        'OrderPlaced' => ['$ref' => '#/components/messages/OrderPlaced'],
                    ],
                ],
            ],
            'operations' => [
                'sendOrderPlaced' => [
                    'action' => 'send',
                    'channel' => ['$ref' => '#/channels/orders'],
                    'summary' => 'Publish an order',
                    'messages' => [['$ref' => '#/channels/orders/messages/OrderPlaced']],
                ],
            ],
            'components' => [
                'messages' => [
                    'OrderPlaced' => [
                        'name' => 'OrderPlaced',
                        'summary' => 'An order was placed',
                        'contentType' => 'application/json',
                        'payload' => ['type' => 'object'],
                        'tags' => [['name' => 'order']],
                    ],
                ],
            ],
            'x-audience' => 'public',
        ], $document->toArray());
    }
}
