<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Discovery;

use Zeusi\AsyncApiBundle\Document\CorrelationId;
use Zeusi\AsyncApiBundle\Document\ExternalDocumentation;
use Zeusi\AsyncApiBundle\Document\OperationAction;
use Zeusi\AsyncApiBundle\Document\Tag;

/**
 * The normalized output of discovery: one message and its placement.
 *
 * Decoupled from how it was found (attribute, routing config, registry…). The
 * assembler turns a set of these into channels, operations, and messages.
 */
final class DiscoveredMessage
{
    /**
     * @param class-string $messageClass
     * @param list<string|Tag> $tags
     */
    public function __construct(
        public readonly string $messageClass,
        public readonly OperationAction $action,
        public readonly ?string $channel,
        public readonly string $name,
        public readonly ?string $title,
        public readonly ?string $summary,
        public readonly ?string $description,
        public readonly ?string $contentType,
        public readonly array $tags,
        public readonly ?ExternalDocumentation $externalDocs = null,
        public readonly ?CorrelationId $correlationId = null,
    ) {}
}
