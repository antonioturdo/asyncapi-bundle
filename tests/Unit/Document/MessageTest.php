<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\CorrelationId;
use Zeusi\AsyncApiBundle\Document\ExternalDocumentation;
use Zeusi\AsyncApiBundle\Document\Message;
use Zeusi\AsyncApiBundle\Document\Tag;

final class MessageTest extends TestCase
{
    public function testItRendersTheCalmOptionalFields(): void
    {
        $message = new Message(
            name: 'OrderPlaced',
            correlationId: new CorrelationId(location: '$message.header#/correlationId', description: 'Correlation key'),
            externalDocs: new ExternalDocumentation(url: 'https://docs.acme.example/order-placed'),
        );
        $message->tags = [new Tag(name: 'order')];

        self::assertSame([
            'name' => 'OrderPlaced',
            'contentType' => 'application/json',
            'correlationId' => ['location' => '$message.header#/correlationId', 'description' => 'Correlation key'],
            'tags' => [['name' => 'order']],
            'externalDocs' => ['url' => 'https://docs.acme.example/order-placed'],
        ], $message->toArray());
    }
}
