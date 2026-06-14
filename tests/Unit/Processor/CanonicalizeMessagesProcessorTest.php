<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Channel;
use Zeusi\AsyncApiBundle\Document\Message;
use Zeusi\AsyncApiBundle\Document\Reference;
use Zeusi\AsyncApiBundle\Processor\CanonicalizeMessagesProcessor;
use Zeusi\AsyncApiBundle\Processor\DocumentContext;

final class CanonicalizeMessagesProcessorTest extends TestCase
{
    public function testItHoistsInlineMessagesToComponentsAndLeavesReferencesBehind(): void
    {
        $document = new AsyncApiDocument();
        $channel = new Channel(address: 'orders');
        $message = new Message(name: 'OrderPlaced');
        $channel->messages['OrderPlaced'] = $message;
        $document->channels['orders'] = $channel;

        (new CanonicalizeMessagesProcessor())->process($document, new DocumentContext('default'));

        // The message body now lives in the components catalog.
        self::assertSame($message, $document->components->messages['OrderPlaced']);
        // The channel keeps only a reference to it.
        $reference = $document->channels['orders']->messages['OrderPlaced'];
        self::assertInstanceOf(Reference::class, $reference);
        self::assertSame('#/components/messages/OrderPlaced', $reference->ref);
    }

    public function testItIsIdempotentAndSkipsExistingReferences(): void
    {
        $document = new AsyncApiDocument();
        $channel = new Channel(address: 'orders');
        $channel->messages['OrderPlaced'] = new Message(name: 'OrderPlaced');
        $document->channels['orders'] = $channel;

        $processor = new CanonicalizeMessagesProcessor();
        $processor->process($document, new DocumentContext('default'));
        $processor->process($document, new DocumentContext('default'));

        self::assertCount(1, $document->components->messages);
        self::assertInstanceOf(Reference::class, $document->channels['orders']->messages['OrderPlaced']);
    }
}
