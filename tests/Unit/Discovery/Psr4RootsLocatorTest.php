<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Discovery;

use Composer\Autoload\ClassLoader;
use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Discovery\Psr4RootsLocator;

final class Psr4RootsLocatorTest extends TestCase
{
    public function testItKeepsProjectRootsAndDropsVendorAndOutsidePaths(): void
    {
        $project = (string) realpath(__DIR__ . '/../../..');
        $inProject = (string) realpath(__DIR__ . '/../../Fixtures/Discovery/Sample');
        $inVendor = (string) realpath($project . '/vendor/composer');

        $loader = new ClassLoader();
        $loader->addPsr4('Acme\\App\\', $inProject);
        $loader->addPsr4('Acme\\Vendored\\', $inVendor);

        $roots = (new Psr4RootsLocator())->rootsFrom($loader, $project);

        self::assertContains($inProject, $roots);
        self::assertNotContains($inVendor, $roots);
    }

    public function testLiveRootsResolvesTheProjectRootsFromTheRealAutoloader(): void
    {
        // During PHPUnit the Composer ClassLoader is registered, so this exercises
        // the real composerClassLoader() + liveRoots() path.
        $project = (string) realpath(__DIR__ . '/../../..');

        $roots = (new Psr4RootsLocator())->liveRoots($project);

        self::assertContains((string) realpath($project . '/src'), $roots);

        foreach ($roots as $root) {
            self::assertStringNotContainsString(\DIRECTORY_SEPARATOR . 'vendor' . \DIRECTORY_SEPARATOR, $root);
        }
    }
}
