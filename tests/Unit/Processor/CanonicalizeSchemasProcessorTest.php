<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Channel;
use Zeusi\AsyncApiBundle\Document\Message;
use Zeusi\AsyncApiBundle\Processor\CanonicalizeSchemasProcessor;
use Zeusi\AsyncApiBundle\Processor\DocumentContext;
use Zeusi\AsyncApiBundle\Processor\GenerationState;

final class CanonicalizeSchemasProcessorTest extends TestCase
{
    private GenerationState $state;

    protected function setUp(): void
    {
        $this->state = new GenerationState();
    }

    public function testItHoistsTheDefinitionAndRewritesThePointer(): void
    {
        $document = new AsyncApiDocument();
        $document->components->messages['Car'] = $this->message(
            ['#/definitions/Engine' => \stdClass::class],
            [
                'type' => 'object',
                'properties' => ['engine' => ['$ref' => '#/definitions/Engine']],
                'definitions' => ['Engine' => ['type' => 'object']],
            ],
        );

        $this->process($document);

        self::assertSame(['Engine' => ['type' => 'object']], $document->components->schemas);
        self::assertSame(
            ['type' => 'object', 'properties' => ['engine' => ['$ref' => '#/components/schemas/Engine']]],
            $document->components->messages['Car']->payload,
        );
    }

    public function testATypeSharedByTwoMessagesIsDefinedOnce(): void
    {
        $document = new AsyncApiDocument();

        foreach (['Car', 'Boat'] as $key) {
            $document->components->messages[$key] = $this->message(
                ['#/definitions/Engine' => \stdClass::class],
                [
                    'properties' => ['engine' => ['$ref' => '#/definitions/Engine']],
                    'definitions' => ['Engine' => ['type' => 'object']],
                ],
            );
        }

        $this->process($document);

        self::assertCount(1, $document->components->schemas);

        foreach (['Car', 'Boat'] as $key) {
            self::assertSame(
                ['$ref' => '#/components/schemas/Engine'],
                $document->components->messages[$key]->payload['properties']['engine'] ?? null,
            );
        }
    }

    public function testItRejectsTwoClassesClaimingTheSameName(): void
    {
        $document = new AsyncApiDocument();
        $document->components->messages['Invoice'] = $this->message(
            ['#/definitions/Money' => \stdClass::class],
            ['definitions' => ['Money' => ['type' => 'object']]],
        );
        $document->components->messages['Payout'] = $this->message(
            ['#/definitions/Money' => \ArrayObject::class],
            ['definitions' => ['Money' => ['type' => 'string']]],
        );

        $this->expectException(\LogicException::class);
        // The error names both classes, so the one to rename is obvious.
        $this->expectExceptionMessage('"Money" is claimed by both stdClass and ArrayObject');

        $this->process($document);
    }

    public function testADeclaredNameIsUsedForTheClass(): void
    {
        $document = new AsyncApiDocument();
        $document->components->messages['Invoice'] = $this->message(
            ['#/definitions/Money' => \stdClass::class],
            [
                'properties' => ['total' => ['$ref' => '#/definitions/Money']],
                'definitions' => ['Money' => ['type' => 'object']],
            ],
        );

        $this->process($document, [\stdClass::class => 'AccountingMoney']);

        self::assertArrayHasKey('AccountingMoney', $document->components->schemas);
        self::assertSame(
            ['$ref' => '#/components/schemas/AccountingMoney'],
            $document->components->messages['Invoice']->payload['properties']['total'] ?? null,
        );
    }

    public function testItRepointsTheRootReferenceOfARecursiveType(): void
    {
        $document = new AsyncApiDocument();
        // A self-referencing type points at its own schema root as `#`, which would
        // otherwise resolve against the document root.
        $document->components->messages['Node'] = $this->message(
            [],
            ['properties' => ['next' => ['$ref' => '#']]],
        );

        $this->process($document);

        self::assertSame(
            ['$ref' => '#/components/messages/Node/payload'],
            $document->components->messages['Node']->payload['properties']['next'] ?? null,
        );
    }

    /**
     * Dropping the canonicalization of messages is a supported way to keep them
     * inline on their channel, so the definitions they reference are hoisted from
     * there too, and the pointers are relative to that position.
     */
    public function testItHandlesMessagesLeftInlineOnTheirChannel(): void
    {
        $document = new AsyncApiDocument();
        $channel = new Channel(address: 'garage');
        $channel->messages['Car'] = $this->message(
            ['#/definitions/Engine' => \stdClass::class],
            [
                'properties' => [
                    'engine' => ['$ref' => '#/definitions/Engine'],
                    'towing' => ['$ref' => '#'],
                ],
                'definitions' => ['Engine' => ['type' => 'object']],
            ],
        );
        $document->channels['garage'] = $channel;

        $this->process($document);

        $message = $document->channels['garage']->messages['Car'];
        self::assertInstanceOf(Message::class, $message);
        $payload = $message->payload;

        self::assertSame(['Engine' => ['type' => 'object']], $document->components->schemas);
        self::assertSame(['$ref' => '#/components/schemas/Engine'], $payload['properties']['engine'] ?? null);
        self::assertSame(['$ref' => '#/channels/garage/messages/Car/payload'], $payload['properties']['towing'] ?? null);
    }

    /**
     * @param array<string, class-string> $refs
     * @param array<string, mixed>        $payload
     */
    private function message(array $refs, array $payload): Message
    {
        $message = new Message();
        $message->payload = $payload;
        $this->state->recordPayloadRefs($message, $refs);

        return $message;
    }

    /**
     * @param array<class-string, string> $names
     */
    private function process(AsyncApiDocument $document, array $names = []): void
    {
        (new CanonicalizeSchemasProcessor($names))->process($document, new DocumentContext('default'), $this->state);
    }
}
