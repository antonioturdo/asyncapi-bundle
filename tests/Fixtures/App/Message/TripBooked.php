<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Fixtures\App\Message;

use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;

#[AsyncApiMessage(channel: 'bookings', summary: 'A trip was booked', tags: ['travel'])]
final class TripBooked
{
    public function __construct(
        public readonly string $bookingId,
        public readonly int $travelers,
    ) {}
}
