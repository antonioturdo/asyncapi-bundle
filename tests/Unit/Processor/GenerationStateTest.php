<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Channel;
use Zeusi\AsyncApiBundle\Document\Message;
use Zeusi\AsyncApiBundle\Processor\CanonicalizeMessagesProcessor;
use Zeusi\AsyncApiBundle\Processor\DocumentContext;
use Zeusi\AsyncApiBundle\Processor\GenerationState;

final class GenerationStateTest extends TestCase
{
    public function testItRecordsThePayloadClassPerMessageAndKeysItByIdentity(): void
    {
        $state = new GenerationState();

        $recorded = new Message(name: 'OrderPlaced');
        // Same name, different object: metadata is per instance, not per name.
        $twin = new Message(name: 'OrderPlaced');

        $state->recordPayloadClass($recorded, \stdClass::class);

        self::assertSame(\stdClass::class, $state->payloadClass($recorded));
        self::assertNull($state->payloadClass($twin));
    }

    /**
     * The reason entries are keyed by identity: a message is moved out of its
     * channel and into the components catalog part-way through the pipeline, and
     * the processors running after that must still resolve its metadata.
     */
    public function testMetadataFollowsAMessageHoistedIntoComponents(): void
    {
        $state = new GenerationState();

        $message = new Message(name: 'OrderPlaced');
        $state->recordPayloadClass($message, \stdClass::class);

        $channel = new Channel(address: 'orders');
        $channel->messages['OrderPlaced'] = $message;
        $document = new AsyncApiDocument();
        $document->channels['orders'] = $channel;

        (new CanonicalizeMessagesProcessor())->process($document, new DocumentContext('default'), $state);

        $hoisted = $document->components->messages['OrderPlaced'] ?? null;
        self::assertInstanceOf(Message::class, $hoisted);
        self::assertSame(\stdClass::class, $state->payloadClass($hoisted));
    }
}
