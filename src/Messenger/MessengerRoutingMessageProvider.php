<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Messenger;

use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;
use Zeusi\AsyncApiBundle\Discovery\DiscoveredMessage;
use Zeusi\AsyncApiBundle\Discovery\MessageProviderInterface;
use Zeusi\AsyncApiBundle\Document\OperationAction;

/**
 * Discovers messages from the Messenger routing map, for projects that prefer not
 * to annotate every routed class.
 *
 * Two tiers:
 *  - exact (default): the routing keys that are concrete classes — the FQCNs you
 *    already wrote in `framework.messenger.routing`. No class scan, no risk.
 *  - patterns (opt-in): scanned classes matched by a rule more specific than `*`
 *    (interface, parent, or namespace wildcard). Broader, noisier.
 *
 * The catch-all `*` is never a discovery source: it matches every dispatched
 * object and cannot be enumerated. Classes carrying `#[AsyncApiMessage]` are left
 * to the attribute provider, which owns their metadata.
 *
 * Discovered messages are "bare": payload (derived downstream from the class),
 * server and content type (from the Messenger enrichment), but no human metadata —
 * the name falls back to the class short name and the placement defaults to send.
 */
final class MessengerRoutingMessageProvider implements MessageProviderInterface
{
    private readonly RouteResolver $routes;

    /**
     * @param array<string, list<string>> $routing         message type => transport names
     * @param iterable<string>             $scannedClasses  candidate FQCNs for the patterns tier
     */
    public function __construct(
        private readonly array $routing,
        private readonly bool $patterns = false,
        private readonly iterable $scannedClasses = [],
    ) {
        $this->routes = new RouteResolver($routing);
    }

    public function provide(): iterable
    {
        foreach ($this->candidates() as $class) {
            if (!class_exists($class)) {
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

    /**
     * @return iterable<string>
     */
    private function candidates(): iterable
    {
        if (!$this->patterns) {
            // Exact tier: the routing keys themselves (interfaces and wildcards are
            // filtered out by the class_exists check in provide()).
            foreach (array_keys($this->routing) as $key) {
                if ($key !== '*') {
                    yield $key;
                }
            }

            return;
        }

        // Patterns tier: scanned classes routed by a rule more specific than `*`.
        foreach ($this->scannedClasses as $class) {
            if ($this->routes->matchesExplicitly($class)) {
                yield $class;
            }
        }
    }
}
