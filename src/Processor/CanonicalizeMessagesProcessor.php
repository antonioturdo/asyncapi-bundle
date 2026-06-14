<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Processor;

use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Message;
use Zeusi\AsyncApiBundle\Document\Reference;

/**
 * Canonicalizes messages into the reusable components catalog.
 *
 * Each inline Message declared on a channel is hoisted into
 * `components.messages` (keyed by its key) and replaced, in the channel, by a
 * Reference to it — so renderers show a single shared "Messages" section. This
 * is an opinionated policy, kept out of the model: drop or replace this processor
 * to emit inline messages (or a different reference strategy) instead.
 */
final class CanonicalizeMessagesProcessor implements AsyncApiProcessorInterface
{
    public function process(AsyncApiDocument $document, DocumentContext $context): void
    {
        foreach ($document->channels as $channel) {
            foreach ($channel->messages as $key => $message) {
                if (!$message instanceof Message) {
                    continue;
                }

                $document->components->messages[$key] = $message;
                $channel->messages[$key] = Reference::to('#/components/messages/' . $key);
            }
        }
    }
}
