<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\AsyncApiVersion;

final class AsyncApiVersionTest extends TestCase
{
    public function testDefaultTargetsThe3xLineAndIsSupported(): void
    {
        $supported = array_map(static fn(AsyncApiVersion $case): string => $case->value, AsyncApiVersion::cases());

        self::assertStringStartsWith('3.', AsyncApiVersion::default()->value);
        self::assertContains(AsyncApiVersion::default()->value, $supported);
    }

    public function testItRejectsUnsupportedVersions(): void
    {
        $this->expectException(\ValueError::class);
        AsyncApiVersion::fromVersion('2.6.0');
    }
}
