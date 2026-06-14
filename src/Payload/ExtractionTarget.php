<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Payload;

/**
 * The extraction-relevant view of a message.
 *
 * What an ExtractionContextFactory needs to decide how a payload should be
 * derived. Deliberately scoped to the payload class — AsyncAPI concerns
 * (channel, tags, summary…) do not influence how the PHP object serializes, so
 * they are kept out. Grows append-only.
 */
final class ExtractionTarget
{
    public function __construct(
        public readonly string $payloadClass,
    ) {}
}
