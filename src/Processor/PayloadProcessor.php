<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Processor;

use Psr\Log\LoggerInterface;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Message;
use Zeusi\AsyncApiBundle\Payload\ExtractionContextFactory;
use Zeusi\AsyncApiBundle\Payload\ExtractionTarget;
use Zeusi\JsonSchemaExtractor\SchemaExtractor;

/**
 * Fills each message's payload with the JSON Schema derived from its source class.
 *
 * Uses json-schema-extractor, and runs after discovery has created the messages.
 * The extractor is optional: without it (the library/bundle is not wired)
 * payloads are simply left empty.
 *
 * An optional ExtractionContextFactory supplies the per-target ExtractionContext
 * (e.g. serialization groups) passed to the extractor.
 *
 * A single class that fails to extract is skipped rather than failing the whole
 * document.
 */
final class PayloadProcessor implements AsyncApiProcessorInterface
{
    public function __construct(
        private readonly ?SchemaExtractor $extractor = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?ExtractionContextFactory $contextFactory = null,
    ) {}

    public function process(AsyncApiDocument $document, DocumentContext $context): void
    {
        if ($this->extractor === null) {
            return;
        }

        foreach ($document->channels as $channel) {
            foreach ($channel->messages as $message) {
                // Runs before canonicalization, so channels still hold inline
                // Messages; skip anything already turned into a Reference.
                if (!$message instanceof Message || $message->payloadClass === null) {
                    continue;
                }

                $extractionContext = $this->contextFactory?->create(
                    new ExtractionTarget($message->payloadClass),
                    $context,
                );

                try {
                    $schema = $this->extractor->extract($message->payloadClass, $extractionContext);
                } catch (\Throwable $e) {
                    $this->logger?->warning(
                        'Could not extract the AsyncAPI payload for {class}: {reason}',
                        ['class' => $message->payloadClass, 'reason' => $e->getMessage(), 'exception' => $e],
                    );

                    continue;
                }

                if (\is_array($schema)) {
                    $message->payload = $schema;
                }
            }
        }
    }
}
