<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Processor;

/**
 * Per-run context handed to every processor.
 *
 * Carries which named document is being generated, so the same processors can
 * serve multiple documents (each with its own filters), à la NelmioApiDocBundle
 * areas.
 */
final class DocumentContext
{
    public function __construct(
        public readonly string $documentName,
    ) {}
}
