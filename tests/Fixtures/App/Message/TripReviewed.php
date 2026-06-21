<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Fixtures\App\Message;

use Symfony\Component\Validator\Constraints as Assert;
use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;

#[AsyncApiMessage(channel: 'reviews', summary: 'A trip was reviewed')]
final class TripReviewed
{
    public function __construct(
        #[Assert\NotBlank]
        #[Assert\Length(min: 3, max: 280)]
        public readonly string $comment,
    ) {}
}
