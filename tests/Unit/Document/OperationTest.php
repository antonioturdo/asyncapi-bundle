<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\ExternalDocumentation;
use Zeusi\AsyncApiBundle\Document\Operation;
use Zeusi\AsyncApiBundle\Document\OperationAction;
use Zeusi\AsyncApiBundle\Document\Reference;
use Zeusi\AsyncApiBundle\Document\Tag;

final class OperationTest extends TestCase
{
    public function testFromArrayResolvesRefsAndKeepsTheRestAsPassthrough(): void
    {
        $operation = Operation::fromArray([
            'action' => 'receive',
            'channel' => ['$ref' => '#/channels/orders'],
            'messages' => [['$ref' => '#/channels/orders/messages/OrderPlaced']],
            'security' => [['$ref' => '#/components/securitySchemes/user']],
        ]);

        self::assertSame(OperationAction::Receive, $operation->action);
        self::assertSame('#/channels/orders', $operation->channel->ref);
        self::assertCount(1, $operation->messages);
        self::assertSame('#/channels/orders/messages/OrderPlaced', $operation->messages[0]->ref);
        self::assertSame([['$ref' => '#/components/securitySchemes/user']], $operation->passthrough['security']);

        self::assertSame([
            'action' => 'receive',
            'channel' => ['$ref' => '#/channels/orders'],
            'messages' => [['$ref' => '#/channels/orders/messages/OrderPlaced']],
            'security' => [['$ref' => '#/components/securitySchemes/user']],
        ], $operation->toArray());
    }

    public function testItRendersTheCalmOptionalFields(): void
    {
        $operation = new Operation(
            action: OperationAction::Send,
            channel: Reference::to('#/channels/orders'),
            externalDocs: new ExternalDocumentation(url: 'https://docs.acme.example/publish'),
        );
        $operation->tags = [new Tag(name: 'orders')];

        self::assertSame([
            'action' => 'send',
            'channel' => ['$ref' => '#/channels/orders'],
            'tags' => [['name' => 'orders']],
            'externalDocs' => ['url' => 'https://docs.acme.example/publish'],
        ], $operation->toArray());
    }
}
