<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Fixtures\Payload;

/**
 * A nested type the extractor emits as a local definition.
 */
final class Engine
{
    public function __construct(
        public readonly string $id,
    ) {}
}
