<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Payload;

use Zeusi\AsyncApiBundle\Processor\DocumentContext;
use Zeusi\JsonSchemaExtractor\Context\ExtractionContext;

/**
 * Builds the ExtractionContext passed to the SchemaExtractor for a given target.
 *
 * Resolved per target, in a given document. This is the seam where
 * extractor-specific knowledge lives (e.g. which Symfony Serializer groups select
 * the payload's shape): the bundle stays agnostic and only consults the
 * user-provided implementation. Returning null means "no context" — the extractor
 * is called as if no factory were configured.
 */
interface ExtractionContextFactory
{
    public function create(ExtractionTarget $target, DocumentContext $document): ?ExtractionContext;
}
