<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Integration\DependencyInjection;

use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Zeusi\AsyncApiBundle\AsyncApiBundle;
use Zeusi\AsyncApiBundle\DependencyInjection\MessengerWiringPass;
use Zeusi\AsyncApiBundle\Messenger\MessengerServerProcessor;

final class MessengerWiringPassTest extends TestCase
{
    public function testItRegistersTheProcessorFromTransportsAndRouting(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(AsyncApiBundle::PARAM_MESSENGER_ENRICHMENT, true);
        $container->register('messenger.senders_locator')
            ->setArgument(0, [\stdClass::class => ['events']]);
        $container->register('messenger.transport.events')
            ->setArguments(['amqp://broker:5672', ['transport_name' => 'events']])
            ->addTag('messenger.receiver', ['alias' => 'events']);
        $container->register('messenger.transport.internal')
            ->setArguments(['sync://'])
            ->addTag('messenger.receiver', ['alias' => 'internal']);
        // Infrastructure service named like a transport but not a receiver.
        $container->register('messenger.transport.symfony_serializer')
            ->setArguments([new Reference('serializer'), 'json']);

        (new MessengerWiringPass())->process($container);

        self::assertTrue($container->hasDefinition('asyncapi.messenger.server_processor'));
        $definition = $container->getDefinition('asyncapi.messenger.server_processor');

        self::assertSame(MessengerServerProcessor::class, $definition->getClass());
        // The serializer service is excluded; real transports keep their DSNs.
        self::assertSame(
            ['events' => 'amqp://broker:5672', 'internal' => 'sync://'],
            $definition->getArgument('$transports'),
        );
        self::assertSame([\stdClass::class => ['events']], $definition->getArgument('$routing'));
        self::assertSame([['priority' => 400]], $definition->getTag(AsyncApiBundle::TAG_PROCESSOR));
        // Discovery is off by default → no routing provider.
        self::assertFalse($container->hasDefinition('asyncapi.messenger.routing_provider'));
    }

    public function testItDoesNothingWhenNeitherCapabilityIsEnabled(): void
    {
        // No enrichment/discovery parameters set → both opt-in capabilities are off.
        $container = new ContainerBuilder();
        $container->register('messenger.senders_locator')->setArgument(0, [\stdClass::class => ['events']]);
        $container->register('messenger.transport.events')
            ->setArguments(['amqp://broker:5672'])
            ->addTag('messenger.receiver', ['alias' => 'events']);

        (new MessengerWiringPass())->process($container);

        self::assertFalse($container->hasDefinition('asyncapi.messenger.server_processor'));
        self::assertFalse($container->hasDefinition('asyncapi.messenger.routing_provider'));
    }

    public function testTheAllowlistRestrictsDocumentedTransports(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(AsyncApiBundle::PARAM_MESSENGER_ENRICHMENT, true);
        $container->setParameter(AsyncApiBundle::PARAM_MESSENGER_TRANSPORTS, ['events']);
        $container->register('messenger.senders_locator')
            ->setArgument(0, [\stdClass::class => ['events', 'internal']]);
        $container->register('messenger.transport.events')
            ->setArguments(['amqp://broker:5672'])
            ->addTag('messenger.receiver', ['alias' => 'events']);
        $container->register('messenger.transport.internal')
            ->setArguments(['amqp://broker:5672/internal'])
            ->addTag('messenger.receiver', ['alias' => 'internal']);

        (new MessengerWiringPass())->process($container);

        $definition = $container->getDefinition('asyncapi.messenger.server_processor');
        self::assertSame(['events' => 'amqp://broker:5672'], $definition->getArgument('$transports'));
        // Routing is scoped too: the non-allowed transport is dropped from the map.
        self::assertSame([\stdClass::class => ['events']], $definition->getArgument('$routing'));
    }

    public function testItRegistersTheRoutingProviderWhenDiscoveryIsEnabled(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(AsyncApiBundle::PARAM_MESSENGER_DISCOVERY, true);
        $container->setParameter('kernel.project_dir', sys_get_temp_dir());
        $container->register('messenger.senders_locator')
            ->setArgument(0, [\stdClass::class => ['events']]);
        $container->register('messenger.transport.events')
            ->setArguments(['amqp://broker:5672'])
            ->addTag('messenger.receiver', ['alias' => 'events']);

        (new MessengerWiringPass())->process($container);

        self::assertTrue($container->hasDefinition('asyncapi.messenger.routing_provider'));
        $provider = $container->getDefinition('asyncapi.messenger.routing_provider');
        self::assertSame([\stdClass::class => ['events']], $provider->getArgument('$routing'));
        self::assertTrue($provider->getArgument('$patterns'));
        self::assertSame([[]], $provider->getTag(AsyncApiBundle::TAG_MESSAGE_PROVIDER));
    }

    public function testItDerivesContentTypePerTransportFromTheSerializer(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(AsyncApiBundle::PARAM_MESSENGER_ENRICHMENT, true);
        $container->register('messenger.senders_locator')->setArgument(0, []);

        // JSON-family transport: serializer is Messenger's Serializer; its format drives the content type.
        $container->register('app.symfony_serializer', 'Symfony\\Component\\Messenger\\Transport\\Serialization\\Serializer')
            ->setArguments([new Reference('serializer'), 'xml']);
        $container->register('messenger.transport.events')
            ->setArguments(['amqp://broker:5672', [], new Reference('app.symfony_serializer')])
            ->addTag('messenger.receiver', ['alias' => 'events']);

        // PhpSerializer transport: no derivable public content type.
        $container->register('app.php_serializer', 'Symfony\\Component\\Messenger\\Transport\\Serialization\\PhpSerializer');
        $container->register('messenger.transport.internal')
            ->setArguments(['sync://', [], new Reference('app.php_serializer')])
            ->addTag('messenger.receiver', ['alias' => 'internal']);

        (new MessengerWiringPass())->process($container);

        self::assertSame(
            ['events' => 'application/xml'],
            $container->getDefinition('asyncapi.messenger.server_processor')->getArgument('$contentTypes'),
        );
    }

    public function testItExcludesFailureTransports(): void
    {
        $container = new ContainerBuilder();
        $container->setParameter(AsyncApiBundle::PARAM_MESSENGER_ENRICHMENT, true);
        $container->register('messenger.senders_locator')->setArgument(0, []);
        $container->register('messenger.transport.events')
            ->setArguments(['amqp://broker:5672'])
            ->addTag('messenger.receiver', ['alias' => 'events', 'is_failure_transport' => false]);
        $container->register('messenger.transport.failed')
            ->setArguments(['amqp://broker:5672/failed'])
            ->addTag('messenger.receiver', ['alias' => 'failed', 'is_failure_transport' => true]);

        (new MessengerWiringPass())->process($container);

        // The failure transport is never a contract → no DSN carried over.
        self::assertSame(
            ['events' => 'amqp://broker:5672'],
            $container->getDefinition('asyncapi.messenger.server_processor')->getArgument('$transports'),
        );
    }

    public function testItDoesNothingWhenMessengerIsNotConfigured(): void
    {
        $container = new ContainerBuilder();

        (new MessengerWiringPass())->process($container);

        self::assertFalse($container->hasDefinition('asyncapi.messenger.server_processor'));
    }
}
