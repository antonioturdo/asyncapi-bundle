<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Messenger;

use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;
use Zeusi\AsyncApiBundle\Discovery\DiscoveredMessage;
use Zeusi\AsyncApiBundle\Discovery\MessageProviderInterface;
use Zeusi\AsyncApiBundle\Document\OperationAction;

/**
 * Discovers messages from the Messenger routing map.
 *
 * Candidates are the scanned project classes routed by a rule more specific than the `*` catch-all:
 * their exact class, a parent, an interface, or a namespace wildcard.
 * The catch-all is never a source (it matches every dispatched object and cannot be enumerated),
 * and classes carrying `#[AsyncApiMessage]` are left to the attribute provider, which owns their metadata.
 *
 * Discovered messages are "bare": payload (derived downstream from the class),
 * server and content type (from the Messenger enrichment), but no human metadata —
 * the name falls back to the class short name and the placement defaults to send.
 */
final class MessengerRoutingMessageProvider implements MessageProviderInterface
{
    /**
     * @param iterable<string> $scannedClasses candidate FQCNs to match against routing
     */
    public function __construct(
        private readonly RouteResolver $routeResolver,
        private readonly iterable $scannedClasses = [],
    ) {}

    public function provide(): iterable
    {
        foreach ($this->scannedClasses as $class) {
            // Routed by a rule more specific than the `*` catch-all.
            if (!class_exists($class) || !$this->routeResolver->matchesExplicitly($class)) {
                continue;
            }

            $reflection = new \ReflectionClass($class);

            // Abstracts are not dispatchable messages; annotated classes belong to
            // the attribute provider, which carries their declared metadata.
            if ($reflection->isAbstract() || $reflection->getAttributes(AsyncApiMessage::class) !== []) {
                continue;
            }

            yield new DiscoveredMessage(
                messageClass: $class,
                action: OperationAction::Send,
                channel: null,
                name: $reflection->getShortName(),
                title: null,
                summary: null,
                description: null,
                contentType: null,
                tags: [],
            );
        }
    }
}
