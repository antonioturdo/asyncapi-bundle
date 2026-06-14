<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Processor;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Processor\DocumentContext;
use Zeusi\AsyncApiBundle\Processor\VersionValidationProcessor;

final class VersionValidationProcessorTest extends TestCase
{
    public function testItAcceptsASupportedVersion(): void
    {
        $document = new AsyncApiDocument();
        $document->asyncapi = '3.0.0';

        (new VersionValidationProcessor())->process($document, new DocumentContext('default'));

        // No exception: the version is supported.
        self::assertSame('3.0.0', $document->asyncapi);
    }

    public function testItRejectsAnUnsupportedVersion(): void
    {
        $document = new AsyncApiDocument();
        $document->asyncapi = '2.6.0';

        $this->expectException(\InvalidArgumentException::class);

        (new VersionValidationProcessor())->process($document, new DocumentContext('default'));
    }
}
