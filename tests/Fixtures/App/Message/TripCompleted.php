<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Fixtures\App\Message;

use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;

#[AsyncApiMessage(channel: 'travels', summary: 'A trip was completed', tags: ['travel'])]
final class TripCompleted
{
    /**
     * @param list<string> $sights
     */
    public function __construct(
        public readonly string $country = 'Egypt',
        public readonly string $city = 'Cairo',
        public readonly array $sights = [
            'Pyramids of Giza',
            'The Sphinx',
            'Egyptian Museum',
            'The Nile',
            'Khan el-Khalili',
        ],
    ) {}
}
