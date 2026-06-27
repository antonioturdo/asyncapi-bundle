<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Messenger;

use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\Handler\HandlersLocator;

/**
 * Resolves a message class to its Messenger transports, faithful to its routing.
 *
 * It reuses {@see HandlersLocator::listTypes()} — the very logic Messenger uses to
 * derive the candidate types (exact class, parents, interfaces, namespace
 * wildcards, then the `*` catch-all) — and applies {@see \Symfony\Component\Messenger\Transport\Sender\SendersLocator::getSenders()}'s
 * selection: transports matched by a concrete type are unioned, while wildcard and
 * `*` entries act as a fallback used only when nothing more specific matched.
 *
 * Unlike Messenger's SendersLocator it works from the class name alone (via a
 * constructor-less instance) and never instantiates the transports.
 */
final class RouteResolver
{
    /**
     * @param array<string, list<string>> $routing message type (class/interface/wildcard/`*`) => transport names
     */
    public function __construct(private readonly array $routing) {}

    /**
     * @return list<string> the transport names the message is routed to
     */
    public function resolve(string $class): array
    {
        $matched = [];

        foreach ($this->candidateTypes($class) as $type) {
            // Wildcard and `*` entries only apply when nothing concrete matched.
            if (str_ends_with($type, '*') && $matched !== []) {
                continue;
            }

            foreach ($this->routing[$type] ?? [] as $transport) {
                if (!\in_array($transport, $matched, true)) {
                    $matched[] = $transport;
                }
            }
        }

        return $matched;
    }

    /**
     * Whether the class is routed by a rule more specific than the `*` catch-all.
     *
     * That is, by its exact class, a parent, an interface, or a namespace wildcard.
     * Used by routing-based discovery to avoid treating every class as a message
     * just because a catch-all transport exists.
     */
    public function matchesExplicitly(string $class): bool
    {
        foreach ($this->candidateTypes($class) as $type) {
            if ($type !== '*' && isset($this->routing[$type])) {
                return true;
            }
        }

        return false;
    }

    /**
     * The ordered candidate types for a message, as Messenger would list them.
     *
     * @return list<string>
     */
    private function candidateTypes(string $class): array
    {
        if (!class_exists($class)) {
            // Not introspectable (e.g. removed class): fall back to exact + catch-all.
            return [$class, '*'];
        }

        try {
            $envelope = new Envelope((new \ReflectionClass($class))->newInstanceWithoutConstructor());

            return array_values(HandlersLocator::listTypes($envelope));
        } catch (\Throwable) {
            // Non-instantiable (abstract/enum/interface) or quirky class: approximate
            // the type list without namespace wildcards. Such classes are never
            // dispatched as messages anyway, so the missing wildcards do not matter.
            return [
                $class,
                ...array_values(class_parents($class) ?: []),
                ...array_values(class_implements($class) ?: []),
                '*',
            ];
        }
    }
}
