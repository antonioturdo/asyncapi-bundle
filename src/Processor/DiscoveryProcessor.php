<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Processor;

use Psr\Log\LoggerInterface;
use Zeusi\AsyncApiBundle\Discovery\DiscoveredMessage;
use Zeusi\AsyncApiBundle\Discovery\MessageProviderInterface;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Document\Channel;
use Zeusi\AsyncApiBundle\Document\Message;
use Zeusi\AsyncApiBundle\Document\Operation;
use Zeusi\AsyncApiBundle\Document\Reference;
use Zeusi\AsyncApiBundle\Document\Tag;

/**
 * Turns discovered messages into channels, operations, and messages.
 *
 * Aggregation rules: messages are grouped into channels by their declared
 * channel key; a message with no channel becomes a channel of its own (keyed by
 * the message name). Each (message, action) yields exactly one operation — the
 * opinionated subset of AsyncAPI this generator derives from code.
 */
final class DiscoveryProcessor implements AsyncApiProcessorInterface
{
    /**
     * @param iterable<MessageProviderInterface> $providers
     */
    public function __construct(
        private readonly iterable $providers,
        private readonly ?LoggerInterface $logger = null,
    ) {}

    public function process(AsyncApiDocument $document, DocumentContext $context): void
    {
        /** @var array<string, class-string> $seenBy message key => the class that claimed it */
        $seenBy = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->provide() as $discovered) {
                if (isset($seenBy[$discovered->name]) && $seenBy[$discovered->name] !== $discovered->messageClass) {
                    $this->logger?->warning(
                        'AsyncAPI message name "{name}" is produced by multiple classes; {new} overrides {existing}. Set an explicit name on #[AsyncApiMessage] to disambiguate.',
                        ['name' => $discovered->name, 'new' => $discovered->messageClass, 'existing' => $seenBy[$discovered->name]],
                    );
                }

                $seenBy[$discovered->name] = $discovered->messageClass;
                $this->place($document, $discovered);
            }
        }
    }

    private function place(AsyncApiDocument $document, DiscoveredMessage $discovered): void
    {
        $channelKey = $discovered->channel ?? $discovered->name;
        $messageKey = $discovered->name;

        $channel = $document->channels[$channelKey] ?? new Channel(address: $channelKey);
        $channel->messages[$messageKey] = $this->toMessage($discovered);
        $document->channels[$channelKey] = $channel;

        $operation = new Operation($discovered->action, Reference::to('#/channels/' . $channelKey));
        $operation->messages = [Reference::to('#/channels/' . $channelKey . '/messages/' . $messageKey)];
        $document->operations[$this->operationKey($discovered)] = $operation;
    }

    private function toMessage(DiscoveredMessage $discovered): Message
    {
        $message = new Message(
            name: $discovered->name,
            // Default the human title to the name, so renderers show it in the
            // message body (not just the sidebar). The attribute can override it.
            title: $discovered->title ?? $discovered->name,
            summary: $discovered->summary,
            description: $discovered->description,
            contentType: $discovered->contentType,
            correlationId: $discovered->correlationId,
            externalDocs: $discovered->externalDocs,
        );
        $message->tags = array_map(
            static fn(string|Tag $tag): Tag => $tag instanceof Tag ? $tag : new Tag($tag),
            $discovered->tags,
        );
        $message->payloadClass = $discovered->messageClass;

        return $message;
    }

    private function operationKey(DiscoveredMessage $discovered): string
    {
        return $discovered->action->value . ucfirst($discovered->name);
    }
}
