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

    public function process(AsyncApiDocument $document, DocumentContext $context, GenerationState $state): void
    {
        if ($this->extractor === null) {
            return;
        }

        foreach ($document->channels as $channel) {
            foreach ($channel->messages as $message) {
                // Runs before canonicalization, so channels still hold inline
                // Messages; skip anything already turned into a Reference.
                if (!$message instanceof Message) {
                    continue;
                }

                $payloadClass = $state->payloadClass($message);

                if ($payloadClass === null) {
                    continue;
                }

                $extractionContext = $this->contextFactory?->create(
                    new ExtractionTarget($payloadClass),
                    $context,
                );

                try {
                    $result = $this->extractor->extract($payloadClass, $extractionContext);
                } catch (\Throwable $e) {
                    $this->logger?->warning(
                        'Could not extract the AsyncAPI payload for {class}: {reason}',
                        ['class' => $payloadClass, 'reason' => $e->getMessage(), 'exception' => $e],
                    );

                    continue;
                }

                // An extractor reports an empty schema as an object, so that it encodes
                // to `{}` rather than `[]`. The model holds a payload as an array, so
                // such a schema is reported instead of being dropped unnoticed.
                if (!\is_array($result->schema)) {
                    $this->logger?->warning(
                        'Skipped the AsyncAPI payload for {class}: the extracted schema is empty.',
                        ['class' => $payloadClass],
                    );

                    continue;
                }

                $message->payload = $result->schema;
                // The pointers describe the payload just stored, and are what a later
                // processor needs to relocate the definitions it references.
                $state->recordPayloadRefs($message, $result->refs);
            }
        }
    }
}
