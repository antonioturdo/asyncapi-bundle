<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Fixtures\Payload;

/**
 * A payload holding a nested type.
 */
final class Car
{
    public function __construct(
        public readonly Engine $engine,
    ) {}
}
