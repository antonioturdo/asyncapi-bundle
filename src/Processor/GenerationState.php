<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Processor;

use Zeusi\AsyncApiBundle\Document\Message;

/**
 * Per-run scratchpad shared by the processors.
 *
 * Holds the metadata a processor needs to hand to a later one but that is not part
 * of the AsyncAPI output, keeping the document model free of generation plumbing.
 * Entries are keyed by object identity, so a message keeps its metadata when it is
 * moved around the document (for instance hoisted into the components catalog).
 */
final class GenerationState
{
    /**
     * @var \SplObjectStorage<Message, class-string>
     */
    private \SplObjectStorage $payloadClasses;

    public function __construct()
    {
        $this->payloadClasses = new \SplObjectStorage();
    }

    /**
     * Records the source class a message's payload is derived from.
     *
     * @param class-string $class
     */
    public function recordPayloadClass(Message $message, string $class): void
    {
        $this->payloadClasses[$message] = $class;
    }

    /**
     * The source class of a message's payload, or null for a message that has none.
     *
     * @return class-string|null
     */
    public function payloadClass(Message $message): ?string
    {
        return $this->payloadClasses[$message] ?? null;
    }
}
