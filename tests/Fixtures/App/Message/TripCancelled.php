<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Fixtures\App\Message;

use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;

#[AsyncApiMessage(channel: 'bookings', summary: 'A trip was cancelled')]
final class TripCancelled
{
    public function __construct(
        public readonly string $bookingId,
        public readonly ?string $reason = null,
    ) {}
}
