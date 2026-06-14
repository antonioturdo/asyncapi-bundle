<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Generator;

use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Info;
use Zeusi\AsyncApiBundle\Processor\AsyncApiProcessorInterface;
use Zeusi\AsyncApiBundle\Processor\DocumentContext;

/**
 * Assembles a document by running the ordered processor pipeline.
 *
 * Runs over a fresh, minimally-valid document. The generator itself knows
 * nothing about discovery sources, attributes, or configuration — that all lives
 * in the processors.
 */
final class AsyncApiGenerator
{
    /**
     * @param iterable<AsyncApiProcessorInterface> $processors Ordered by tag priority (highest first).
     */
    public function __construct(
        private readonly iterable $processors,
    ) {}

    public function generate(string $documentName = 'default'): AsyncApiDocument
    {
        // Seed a specification-valid document (info.title/version are the only
        // required fields; the asyncapi version is seeded by the document itself).
        // Config is merged/hydrated on top by the processors.
        $document = new AsyncApiDocument();
        $document->info = new Info($documentName, '1.0.0');
        $context = new DocumentContext($documentName);

        foreach ($this->processors as $processor) {
            $processor->process($document, $context);
        }

        return $document;
    }
}
