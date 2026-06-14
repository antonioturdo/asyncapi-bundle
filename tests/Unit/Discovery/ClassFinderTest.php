<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Discovery;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Discovery\ClassFinder;

final class ClassFinderTest extends TestCase
{
    private const NS = 'Zeusi\\AsyncApiBundle\\Tests\\Fixtures\\Discovery\\Sample';
    private const SAMPLE_DIR = __DIR__ . '/../../Fixtures/Discovery/Sample';

    public function testItFindsEveryDeclaredClassIncludingNonPsr4Ones(): void
    {
        $finder = new ClassFinder();

        // The non-PSR-4 file (renamed_file.php holding RenamedClass) is found from
        // its source tokens, not from the path.
        self::assertSame([
            self::NS . '\\PlainThing',
            self::NS . '\\PublishedThing',
            self::NS . '\\RenamedClass',
        ], $finder->find([self::SAMPLE_DIR]));
    }

    public function testItFiltersByAttributeNameWithoutLoadingClasses(): void
    {
        $finder = new ClassFinder();

        self::assertSame(
            [self::NS . '\\PublishedThing'],
            $finder->find([self::SAMPLE_DIR], [], 'AsyncApiMessage'),
        );
    }

    public function testItReturnsNothingForMissingDirectories(): void
    {
        $finder = new ClassFinder();

        self::assertSame([], $finder->find([self::SAMPLE_DIR . '/does-not-exist']));
    }
}
