<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Discovery;

/**
 * A pluggable source of discovered messages.
 *
 * Implementations may read attributes, routing config, a custom registry, etc. —
 * the assembler consumes them all uniformly through this interface.
 */
interface MessageProviderInterface
{
    /**
     * @return iterable<DiscoveredMessage>
     */
    public function provide(): iterable;
}
