<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\Components;
use Zeusi\AsyncApiBundle\Document\Message;

final class ComponentsTest extends TestCase
{
    public function testEmptyComponentsRenderToAnEmptyArray(): void
    {
        self::assertSame([], (new Components())->toArray());
    }

    public function testItDeepMergesTypedMessagesWithPassthroughComponents(): void
    {
        $components = new Components();
        $components->messages['OrderPlaced'] = new Message(name: 'OrderPlaced');
        // Unmodelled component types come through the local passthrough.
        $components->passthrough['schemas'] = ['Order' => ['type' => 'object']];

        self::assertSame([
            'schemas' => ['Order' => ['type' => 'object']],
            'messages' => ['OrderPlaced' => ['name' => 'OrderPlaced', 'contentType' => 'application/json']],
        ], $components->toArray());
    }
}
