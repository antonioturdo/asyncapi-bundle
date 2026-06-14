<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Processor;

use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;

/**
 * The single public extension point of the generator.
 *
 * Each processor mutates the document in place; the built-in pipeline (config,
 * discovery, payload, bindings) is itself a series of processors. Ordering is
 * controlled by the priority of the service tag, not by the interface, so one
 * class can run at different priorities.
 */
interface AsyncApiProcessorInterface
{
    public function process(AsyncApiDocument $document, DocumentContext $context): void;
}
