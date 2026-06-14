<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Discovery;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;
use Zeusi\AsyncApiBundle\Discovery\AttributeMessageProvider;
use Zeusi\AsyncApiBundle\Document\CorrelationId;
use Zeusi\AsyncApiBundle\Document\ExternalDocumentation;
use Zeusi\AsyncApiBundle\Document\OperationAction;

final class AttributeMessageProviderTest extends TestCase
{
    public function testItReadsTheAttributeAndSkipsUnrelatedOrMissingClasses(): void
    {
        $provider = new AttributeMessageProvider([
            DefaultPlacementMessage::class,
            ExplicitPlacementMessage::class,
            PlainClass::class,
            'Zeusi\\AsyncApiBundle\\Nonexistent',
        ]);

        $discovered = [];

        foreach ($provider->provide() as $message) {
            $discovered[] = $message;
        }

        // Only the two attributed classes are returned, in input order.
        self::assertCount(2, $discovered);

        // Defaults: send action, name falls back to the class short name, JSON content type.
        $default = $discovered[0];
        self::assertSame(DefaultPlacementMessage::class, $default->messageClass);
        self::assertSame(OperationAction::Send, $default->action);
        self::assertNull($default->channel);
        self::assertSame('DefaultPlacementMessage', $default->name);
        self::assertSame('application/json', $default->contentType);

        // Explicit placement and metadata are carried through verbatim.
        $explicit = $discovered[1];
        self::assertSame(OperationAction::Receive, $explicit->action);
        self::assertSame('audit', $explicit->channel);
        self::assertSame('AuditEntryWritten', $explicit->name);
        self::assertSame(['audit', 'security'], $explicit->tags);

        // Typed value objects passed via `new` in the attribute are carried through.
        self::assertInstanceOf(ExternalDocumentation::class, $explicit->externalDocs);
        self::assertSame('https://docs.acme.example/audit', $explicit->externalDocs->url);
        self::assertInstanceOf(CorrelationId::class, $explicit->correlationId);
        self::assertSame('$message.header#/traceId', $explicit->correlationId->location);
    }
}

#[AsyncApiMessage(summary: 'Happens by default')]
final class DefaultPlacementMessage {}

#[AsyncApiMessage(
    channel: 'audit',
    action: OperationAction::Receive,
    name: 'AuditEntryWritten',
    tags: ['audit', 'security'],
    externalDocs: new ExternalDocumentation(url: 'https://docs.acme.example/audit'),
    correlationId: new CorrelationId(location: '$message.header#/traceId'),
)]
final class ExplicitPlacementMessage {}

final class PlainClass {}
