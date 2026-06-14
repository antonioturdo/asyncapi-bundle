<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Discovery\DiscoveredMessage;
use Zeusi\AsyncApiBundle\Discovery\MessageProviderInterface;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Info;
use Zeusi\AsyncApiBundle\Document\OperationAction;
use Zeusi\AsyncApiBundle\Processor\CanonicalizeMessagesProcessor;
use Zeusi\AsyncApiBundle\Processor\DiscoveryProcessor;
use Zeusi\AsyncApiBundle\Processor\DocumentContext;

final class DiscoveryProcessorTest extends TestCase
{
    public function testItWarnsWhenTwoClassesProduceTheSameMessageName(): void
    {
        $logger = new class extends \Psr\Log\AbstractLogger {
            /** @var list<string> */
            public array $warnings = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                if ($level === \Psr\Log\LogLevel::WARNING) {
                    $this->warnings[] = (string) $message;
                }
            }
        };

        $provider = new class implements MessageProviderInterface {
            public function provide(): iterable
            {
                yield new DiscoveredMessage(\stdClass::class, OperationAction::Send, 'a', 'Created', null, null, null, 'application/json', []);
                yield new DiscoveredMessage(\ArrayObject::class, OperationAction::Send, 'b', 'Created', null, null, null, 'application/json', []);
            }
        };

        (new DiscoveryProcessor([$provider], $logger))->process(new AsyncApiDocument(), new DocumentContext('default'));

        self::assertCount(1, $logger->warnings);
    }


    public function testItGroupsByChannelGivesUnchanneledMessagesTheirOwnChannelAndOneOperationPerAction(): void
    {
        $provider = new class implements MessageProviderInterface {
            public function provide(): iterable
            {
                yield new DiscoveredMessage(\stdClass::class, OperationAction::Send, 'orders', 'OrderPlaced', null, 'Order placed', null, 'application/json', ['order']);
                yield new DiscoveredMessage(\stdClass::class, OperationAction::Send, 'orders', 'OrderCancelled', null, null, null, 'application/json', []);
                yield new DiscoveredMessage(\stdClass::class, OperationAction::Send, null, 'UserSignedUp', null, null, null, 'application/json', []);
                yield new DiscoveredMessage(\stdClass::class, OperationAction::Receive, 'audit', 'AuditWritten', null, null, null, 'application/json', []);
            }
        };

        $document = new AsyncApiDocument();
        $document->info = new Info('seed', '1.0.0');

        $context = new DocumentContext('default');
        (new DiscoveryProcessor([$provider]))->process($document, $context);
        // Discovery leaves inline messages; canonicalization hoists them to components.
        (new CanonicalizeMessagesProcessor())->process($document, $context);

        self::assertSame([
            'asyncapi' => '3.1.0',
            'info' => ['title' => 'seed', 'version' => '1.0.0'],
            'channels' => [
                'orders' => [
                    'address' => 'orders',
                    'messages' => [
                        'OrderPlaced' => ['$ref' => '#/components/messages/OrderPlaced'],
                        'OrderCancelled' => ['$ref' => '#/components/messages/OrderCancelled'],
                    ],
                ],
                'UserSignedUp' => [
                    'address' => 'UserSignedUp',
                    'messages' => [
                        'UserSignedUp' => ['$ref' => '#/components/messages/UserSignedUp'],
                    ],
                ],
                'audit' => [
                    'address' => 'audit',
                    'messages' => [
                        'AuditWritten' => ['$ref' => '#/components/messages/AuditWritten'],
                    ],
                ],
            ],
            'operations' => [
                'sendOrderPlaced' => ['action' => 'send', 'channel' => ['$ref' => '#/channels/orders'], 'messages' => [['$ref' => '#/channels/orders/messages/OrderPlaced']]],
                'sendOrderCancelled' => ['action' => 'send', 'channel' => ['$ref' => '#/channels/orders'], 'messages' => [['$ref' => '#/channels/orders/messages/OrderCancelled']]],
                'sendUserSignedUp' => ['action' => 'send', 'channel' => ['$ref' => '#/channels/UserSignedUp'], 'messages' => [['$ref' => '#/channels/UserSignedUp/messages/UserSignedUp']]],
                'receiveAuditWritten' => ['action' => 'receive', 'channel' => ['$ref' => '#/channels/audit'], 'messages' => [['$ref' => '#/channels/audit/messages/AuditWritten']]],
            ],
            'components' => [
                'messages' => [
                    'OrderPlaced' => ['name' => 'OrderPlaced', 'title' => 'OrderPlaced', 'summary' => 'Order placed', 'contentType' => 'application/json', 'tags' => [['name' => 'order']]],
                    'OrderCancelled' => ['name' => 'OrderCancelled', 'title' => 'OrderCancelled', 'contentType' => 'application/json'],
                    'UserSignedUp' => ['name' => 'UserSignedUp', 'title' => 'UserSignedUp', 'contentType' => 'application/json'],
                    'AuditWritten' => ['name' => 'AuditWritten', 'title' => 'AuditWritten', 'contentType' => 'application/json'],
                ],
            ],
        ], $document->toArray());
    }
}
