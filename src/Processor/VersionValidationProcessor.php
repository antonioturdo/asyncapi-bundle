<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Processor;

use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\AsyncApiVersion;

/**
 * Validates that the document targets a supported AsyncAPI version.
 *
 * Checked after everything has been merged. The version lives in the opaque
 * `document` passthrough (it is a field of the document), so it can only be
 * checked once the config and all processors have run — hence a processor that
 * runs last.
 */
final class VersionValidationProcessor implements AsyncApiProcessorInterface
{
    public function process(AsyncApiDocument $document, DocumentContext $context): void
    {
        $version = $document->asyncapi;

        if (AsyncApiVersion::tryFrom($version) === null) {
            throw new \InvalidArgumentException(\sprintf(
                'Unsupported AsyncAPI version "%s". Supported versions: %s.',
                $version,
                implode(', ', array_map(static fn(AsyncApiVersion $v): string => $v->value, AsyncApiVersion::cases())),
            ));
        }
    }
}
