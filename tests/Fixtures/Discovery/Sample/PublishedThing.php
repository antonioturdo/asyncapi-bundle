<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Fixtures\Discovery\Sample;

use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;

#[AsyncApiMessage(summary: 'A published thing')]
final class PublishedThing {}
