<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Zeusi\AsyncApiBundle\AsyncApiBundle;
use Zeusi\AsyncApiBundle\Controller\AsyncApiController;
use Zeusi\AsyncApiBundle\Generator\AsyncApiGenerator;
use Zeusi\AsyncApiBundle\Processor\ConfigProcessor;
use Zeusi\AsyncApiBundle\Processor\PayloadProcessor;

final class AsyncApiBundleTest extends TestCase
{
    public function testItRegistersTheGeneratorPipelineAndController(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());
        $extension = (new AsyncApiBundle())->getContainerExtension();
        self::assertNotNull($extension);

        $extension->load([['document' => ['info' => ['title' => 'Acme Events']]]], $container);

        self::assertTrue($container->hasDefinition(AsyncApiGenerator::class));
        self::assertTrue($container->hasDefinition(AsyncApiController::class));

        // The built-in ConfigProcessor is tagged as a processor and runs first.
        $processorTags = $container->getDefinition(ConfigProcessor::class)->getTag('asyncapi.processor');
        self::assertSame([['priority' => 1000]], $processorTags);

        // The static config fragment is passed through verbatim to the ConfigProcessor.
        self::assertSame(
            ['info' => ['title' => 'Acme Events']],
            $container->getDefinition(ConfigProcessor::class)->getArgument('$document'),
        );

        // The controller is exposed as a routable service.
        self::assertNotSame([], $container->getDefinition(AsyncApiController::class)->getTag('controller.service_arguments'));

        // No context factory configured: the PayloadProcessor argument is left unset.
        self::assertArrayNotHasKey('$contextFactory', $container->getDefinition(PayloadProcessor::class)->getArguments());
    }

    public function testPayloadSchemaExtractorAcceptsTheScalarShorthand(): void
    {
        $container = $this->loadWith(['payload_schema_extractor' => 'app.my_extractor']);

        self::assertSame(
            'app.my_extractor',
            (string) $container->getDefinition(PayloadProcessor::class)->getArgument('$extractor'),
        );
        self::assertArrayNotHasKey('$contextFactory', $container->getDefinition(PayloadProcessor::class)->getArguments());
    }

    public function testPayloadSchemaExtractorWiresTheContextFactoryWhenConfigured(): void
    {
        $container = $this->loadWith([
            'payload_schema_extractor' => [
                'service' => 'app.my_extractor',
                'context_factory' => 'app.my_context_factory',
            ],
        ]);

        self::assertSame(
            'app.my_context_factory',
            (string) $container->getDefinition(PayloadProcessor::class)->getArgument('$contextFactory'),
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function loadWith(array $config): ContainerBuilder
    {
        $container = new ContainerBuilder();
        $container->setParameter('kernel.environment', 'test');
        $container->setParameter('kernel.debug', false);
        $container->setParameter('kernel.build_dir', sys_get_temp_dir());

        $extension = (new AsyncApiBundle())->getContainerExtension();
        self::assertNotNull($extension);
        $extension->load([$config], $container);

        return $container;
    }
}
