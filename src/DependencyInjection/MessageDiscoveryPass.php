<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zeusi\AsyncApiBundle\AsyncApiBundle;
use Zeusi\AsyncApiBundle\Attribute\AsyncApiMessage;
use Zeusi\AsyncApiBundle\Discovery\AttributeMessageProvider;
use Zeusi\AsyncApiBundle\Discovery\ClassFinder;
use Zeusi\AsyncApiBundle\Discovery\Psr4RootsLocator;

/**
 * Runs attribute discovery at container compile time.
 *
 * Scans the configured paths (or the project's PSR-4 roots by default) for
 * classes referencing `#[AsyncApiMessage]` and bakes the candidate FQCNs into
 * the {@see AttributeMessageProvider} service. The authoritative attribute
 * reading still happens at runtime in the provider.
 */
final class MessageDiscoveryPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(AttributeMessageProvider::class)) {
            return;
        }

        $classes = (new ClassFinder())->find($this->scanPaths($container), ['tests'], AsyncApiMessage::class);

        $container->getDefinition(AttributeMessageProvider::class)->setArgument('$classes', $classes);
    }

    /**
     * @return list<string>
     */
    private function scanPaths(ContainerBuilder $container): array
    {
        $configured = $container->hasParameter(AsyncApiBundle::PARAM_DISCOVERY_ATTRIBUTE_PATHS)
            ? $container->getParameter(AsyncApiBundle::PARAM_DISCOVERY_ATTRIBUTE_PATHS)
            : [];

        $paths = \is_array($configured)
            ? array_values(array_filter($configured, 'is_string'))
            : [];

        if ($paths !== []) {
            return $paths;
        }

        $projectDir = $container->hasParameter('kernel.project_dir')
            ? $container->getParameter('kernel.project_dir')
            : null;

        return \is_string($projectDir) ? (new Psr4RootsLocator())->liveRoots($projectDir) : [];
    }
}
