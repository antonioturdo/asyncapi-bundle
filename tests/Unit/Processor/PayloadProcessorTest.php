<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Channel;
use Zeusi\AsyncApiBundle\Document\Info;
use Zeusi\AsyncApiBundle\Document\Message;
use Zeusi\AsyncApiBundle\Payload\ExtractionContextFactory;
use Zeusi\AsyncApiBundle\Payload\ExtractionTarget;
use Zeusi\AsyncApiBundle\Processor\DocumentContext;
use Zeusi\AsyncApiBundle\Processor\PayloadProcessor;
use Zeusi\JsonSchemaExtractor\Context\ExtractionContext;
use Zeusi\JsonSchemaExtractor\SchemaExtractor;

final class PayloadProcessorTest extends TestCase
{
    public function testItFillsPayloadsFromTheExtractorAndLeavesUnsourcedMessagesAlone(): void
    {
        $schema = ['type' => 'object', 'properties' => ['id' => ['type' => 'string']]];

        $extractor = $this->createMock(SchemaExtractor::class);
        $extractor->method('extract')->willReturn($schema);

        $document = $this->documentWith(
            $sourced = $this->message('OrderPlaced', \stdClass::class),
            $unsourced = $this->message('Freeform', null),
        );

        (new PayloadProcessor($extractor))->process($document, new DocumentContext('default'));

        self::assertSame($schema, $sourced->payload);
        self::assertNull($unsourced->payload);
    }

    public function testItLeavesPayloadsEmptyWithoutAnExtractor(): void
    {
        $document = $this->documentWith($message = $this->message('OrderPlaced', \stdClass::class));

        (new PayloadProcessor())->process($document, new DocumentContext('default'));

        self::assertNull($message->payload);
    }

    public function testItSkipsAndWarnsWhenExtractionFails(): void
    {
        $extractor = $this->createMock(SchemaExtractor::class);
        $extractor->method('extract')->willThrowException(new \RuntimeException());

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

        $document = $this->documentWith($message = $this->message('OrderPlaced', \stdClass::class));

        (new PayloadProcessor($extractor, $logger))->process($document, new DocumentContext('default'));

        self::assertNull($message->payload);
        self::assertCount(1, $logger->warnings);
    }

    public function testItPassesTheContextFactoryResultToTheExtractor(): void
    {
        $expectedContext = new ExtractionContext();

        $extractor = $this->createMock(SchemaExtractor::class);
        $extractor->expects(self::once())
            ->method('extract')
            ->with(\stdClass::class, self::identicalTo($expectedContext))
            ->willReturn(['type' => 'object']);

        $factory = new class ($expectedContext) implements ExtractionContextFactory {
            /** @var list<ExtractionTarget> */
            public array $targets = [];

            public function __construct(private readonly ?ExtractionContext $context) {}

            public function create(ExtractionTarget $target, DocumentContext $document): ?ExtractionContext
            {
                $this->targets[] = $target;

                return $this->context;
            }
        };

        $document = $this->documentWith($this->message('OrderPlaced', \stdClass::class));

        (new PayloadProcessor($extractor, null, $factory))->process($document, new DocumentContext('default'));

        self::assertCount(1, $factory->targets);
        self::assertSame(\stdClass::class, $factory->targets[0]->payloadClass);
    }

    /**
     * @param class-string|null $payloadClass
     */
    private function message(string $name, ?string $payloadClass): Message
    {
        $message = new Message(name: $name);
        $message->payloadClass = $payloadClass;

        return $message;
    }

    private function documentWith(Message ...$messages): AsyncApiDocument
    {
        $document = new AsyncApiDocument();
        $document->info = new Info('seed', '1.0.0');
        $channel = new Channel(address: 'orders');

        foreach ($messages as $message) {
            $channel->messages[(string) $message->name] = $message;
        }

        $document->channels['orders'] = $channel;

        return $document;
    }
}
