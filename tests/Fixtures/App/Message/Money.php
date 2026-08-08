<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Fixtures\App\Message;

/**
 * A nested value object (no #[AsyncApiMessage]): referenced by more than one
 * message, so it is published once under components.schemas.
 */
final class Money
{
    public function __construct(
        public readonly int $amount,
        public readonly string $currency,
    ) {}
}
