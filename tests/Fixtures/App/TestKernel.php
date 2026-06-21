<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Fixtures\App;

use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Bundle\TwigBundle\TwigBundle;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\Loader\Configurator\RoutingConfigurator;
use Zeusi\AsyncApiBundle\AsyncApiBundle;
use Zeusi\AsyncApiBundle\Controller\AsyncApiController;

/**
 * Minimal Symfony application used to exercise (and demo) the bundle end to end:
 * it scans the fixture DTOs in Message/, derives payloads through
 * json-schema-extractor, and exposes the document over HTTP.
 */
final class TestKernel extends Kernel
{
    use MicroKernelTrait;

    public function __construct(private readonly string $token = 'app', bool $debug = true)
    {
        parent::__construct('test', $debug);
    }

    public function registerBundles(): iterable
    {
        return [
            new FrameworkBundle(),
            new TwigBundle(),
            new AsyncApiBundle(),
        ];
    }

    public function getCacheDir(): string
    {
        return $this->tmp('cache');
    }

    public function getBuildDir(): string
    {
        return $this->tmp('build');
    }

    public function getLogDir(): string
    {
        return $this->tmp('log');
    }

    protected function configureContainer(ContainerConfigurator $container): void
    {
        $container->extension('framework', [
            'secret' => 'test',
            'test' => true,
            'http_method_override' => false,
            'handle_all_throwables' => true,
            'router' => ['utf8' => true],
            'php_errors' => ['log' => true],
            'serializer' => ['enabled' => true],
            'validation' => ['enabled' => true],
        ]);

        $container->extension('twig', []);

        $container->extension('asyncapi', [
            'document' => [
                'info' => [
                    'title' => 'Demo Events',
                    'version' => '1.0.0',
                    'description' => 'Events emitted by the demo app.',
                ],
                'servers' => [
                    'production' => [
                        'host' => 'broker.demo.test:5672',
                        'protocol' => 'amqp',
                        'description' => 'Demo AMQP broker',
                    ],
                ],
            ],
            'discovery' => [
                'attribute' => [
                    'paths' => [__DIR__ . '/Message'],
                ],
            ],
            'ui' => [
                'config' => [
                    'show' => [
                        'sidebar' => true,
                        'messages' => true,
                        'errors' => true,
                    ],
                ],
            ],
        ]);
    }

    protected function configureRoutes(RoutingConfigurator $routes): void
    {
        $routes->import(AsyncApiController::class, 'attribute');
    }

    private function tmp(string $kind): string
    {
        return sys_get_temp_dir() . '/asyncapi_demo/' . $this->token . '/' . $kind;
    }
}
