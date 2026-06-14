<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Processor;

use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;

/**
 * Merges the static, configuration-authored AsyncAPI fragment into the document.
 *
 * Runs first, so discovery and payload processors build on top of it.
 */
final class ConfigProcessor implements AsyncApiProcessorInterface
{
    /**
     * @param array<array-key, mixed> $document Static AsyncAPI fragment.
     */
    public function __construct(
        private readonly array $document,
    ) {}

    public function process(AsyncApiDocument $document, DocumentContext $context): void
    {
        $document->applyArray($this->document);
    }
}
