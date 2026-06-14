<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Discovery;

use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;

/**
 * Reads `#[AsyncApiMessage]` from a given set of classes into DiscoveredMessages.
 *
 * This is the "read the attribute" half of discovery: it is handed the candidate
 * class names (e.g. by {@see ClassFinder}) and is the authoritative check —
 * classes without the attribute are simply skipped.
 */
final class AttributeMessageProvider implements MessageProviderInterface
{
    /**
     * @param iterable<string> $classes Candidate fully-qualified class names.
     */
    public function __construct(
        private readonly iterable $classes,
    ) {}

    public function provide(): iterable
    {
        foreach ($this->classes as $class) {
            if (!class_exists($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);
            $attributes = $reflection->getAttributes(AsyncApiMessage::class);

            if ($attributes === []) {
                continue;
            }

            $message = $attributes[0]->newInstance();

            yield new DiscoveredMessage(
                messageClass: $class,
                action: $message->action,
                channel: $message->channel,
                name: $message->name ?? $reflection->getShortName(),
                title: $message->title,
                summary: $message->summary,
                description: $message->description,
                contentType: $message->contentType,
                tags: $message->tags,
                externalDocs: $message->externalDocs,
                correlationId: $message->correlationId,
            );
        }
    }
}
