<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Integration\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zeusi\AsyncApiBundle\AsyncApiBundle;
use Zeusi\AsyncApiBundle\DependencyInjection\MessageDiscoveryPass;
use Zeusi\AsyncApiBundle\Discovery\AttributeMessageProvider;

final class MessageDiscoveryPassTest extends TestCase
{
    public function testItBakesDiscoveredCandidateClassesIntoTheProvider(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(AsyncApiBundle::PARAM_DISCOVERY_ATTRIBUTE_PATHS, [__DIR__ . '/../../Fixtures/Discovery/Sample']);
        $container->register(AttributeMessageProvider::class)->setArgument('$classes', []);

        (new MessageDiscoveryPass())->process($container);

        self::assertSame(
            ['Zeusi\\AsyncApiBundle\\Tests\\Fixtures\\Discovery\\Sample\\PublishedThing'],
            $container->getDefinition(AttributeMessageProvider::class)->getArgument('$classes'),
        );
    }

    public function testItDoesNothingWithoutTheProviderDefinition(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(AsyncApiBundle::PARAM_DISCOVERY_ATTRIBUTE_PATHS, [__DIR__ . '/../../Fixtures/Discovery/Sample']);

        (new MessageDiscoveryPass())->process($container);

        self::assertFalse($container->hasDefinition(AttributeMessageProvider::class));
    }
}
