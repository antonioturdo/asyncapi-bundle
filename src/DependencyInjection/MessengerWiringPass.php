<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Zeusi\AsyncApiBundle\AsyncApiBundle;
use Zeusi\AsyncApiBundle\Discovery\ClassFinder;
use Zeusi\AsyncApiBundle\Discovery\Psr4RootsLocator;
use Zeusi\AsyncApiBundle\Messenger\MessengerRoutingMessageProvider;
use Zeusi\AsyncApiBundle\Messenger\MessengerServerProcessor;

/**
 * Wires the Messenger server enrichment when Symfony Messenger is configured.
 *
 * Reads the transport DSNs (from the `messenger.receiver`-tagged transports) and
 * the routing map (from `messenger.senders_locator`) and feeds them to a
 * {@see MessengerServerProcessor}. DSN args are passed through verbatim, so
 * `%env()%` placeholders stay lazy and are resolved at runtime.
 */
final class MessengerWiringPass implements CompilerPassInterface
{
    private const SERVICE = 'asyncapi.messenger.server_processor';
    private const DISCOVERY_SERVICE = 'asyncapi.messenger.routing_provider';
    private const RECEIVER_TAG = 'messenger.receiver';
    private const TRANSPORT_PREFIX = 'messenger.transport.';

    /** Messenger's Symfony-Serializer-based serializer; its format yields a public content type. */
    private const SERIALIZER_CLASS = 'Symfony\\Component\\Messenger\\Transport\\Serialization\\Serializer';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition('messenger.senders_locator')) {
            return;
        }

        // Two independent, opt-in capabilities — both off by default.
        $enrichment = $this->boolParam($container, AsyncApiBundle::PARAM_MESSENGER_ENRICHMENT, false);
        $discovery = $this->boolParam($container, AsyncApiBundle::PARAM_MESSENGER_DISCOVERY, false);

        if (!$enrichment && !$discovery) {
            return;
        }

        $allowed = $this->allowlist($container);
        $routing = $this->filterRouting($this->routing($container), $allowed);

        if ($enrichment) {
            $container->register(self::SERVICE, MessengerServerProcessor::class)
                ->setArgument('$transports', $this->filterKeys($this->transports($container), $allowed))
                ->setArgument('$routing', $routing)
                ->setArgument('$contentTypes', $this->filterKeys($this->contentTypes($container), $allowed))
                ->addTag(AsyncApiBundle::TAG_PROCESSOR, ['priority' => 400]);
        }

        if ($discovery) {
            $container->register(self::DISCOVERY_SERVICE, MessengerRoutingMessageProvider::class)
                ->setArgument('$routing', $routing)
                ->setArgument('$patterns', true)
                ->setArgument('$scannedClasses', $this->scannedClasses($container))
                ->addTag(AsyncApiBundle::TAG_MESSAGE_PROVIDER);
        }
    }

    private function boolParam(ContainerBuilder $container, string $name, bool $default): bool
    {
        return $container->hasParameter($name) ? (bool) $container->getParameter($name) : $default;
    }

    /**
     * Transport allowlist; empty means "all" (failure transports are excluded elsewhere).
     *
     * @return list<string>
     */
    private function allowlist(ContainerBuilder $container): array
    {
        $value = $container->hasParameter(AsyncApiBundle::PARAM_MESSENGER_TRANSPORTS)
            ? $container->getParameter(AsyncApiBundle::PARAM_MESSENGER_TRANSPORTS)
            : [];

        return \is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /**
     * @param array<string, string> $map
     * @param list<string>          $allowed
     * @return array<string, string>
     */
    private function filterKeys(array $map, array $allowed): array
    {
        return $allowed === [] ? $map : array_intersect_key($map, array_flip($allowed));
    }

    /**
     * @param array<string, list<string>> $routing
     * @param list<string>                $allowed
     * @return array<string, list<string>>
     */
    private function filterRouting(array $routing, array $allowed): array
    {
        if ($allowed === []) {
            return $routing;
        }

        $filtered = [];

        foreach ($routing as $type => $transports) {
            $kept = array_values(array_intersect($transports, $allowed));

            if ($kept !== []) {
                $filtered[$type] = $kept;
            }
        }

        return $filtered;
    }

    /**
     * All project classes (PSR-4 roots, excluding tests), the candidate set for the
     * routing-discovery patterns tier.
     *
     * @return list<string>
     */
    private function scannedClasses(ContainerBuilder $container): array
    {
        $projectDir = $container->hasParameter('kernel.project_dir')
            ? $container->getParameter('kernel.project_dir')
            : null;

        if (!\is_string($projectDir)) {
            return [];
        }

        return (new ClassFinder())->find((new Psr4RootsLocator())->liveRoots($projectDir), ['tests']);
    }

    /**
     * The wire content type per transport, derived from its serializer's format —
     * only for transports whose serializer maps to a public format. `PhpSerializer`
     * and custom serializers yield no entry (the message keeps its declared/default type).
     *
     * @return array<string, string> transport name => content type
     */
    private function contentTypes(ContainerBuilder $container): array
    {
        $contentTypes = [];

        foreach ($container->findTaggedServiceIds(self::RECEIVER_TAG) as $id => $tags) {
            if ($this->isFailureTransport($tags)) {
                continue;
            }

            $serializer = $container->getDefinition($id)->getArguments()[2] ?? null;

            if (!$serializer instanceof Reference || !$container->has((string) $serializer)) {
                continue;
            }

            $serializerDefinition = $container->findDefinition((string) $serializer);

            if ($serializerDefinition->getClass() !== self::SERIALIZER_CLASS) {
                continue;
            }

            $format = $serializerDefinition->getArguments()[1] ?? null;
            $contentTypes[$this->transportName($id, $tags)] = 'application/' . (\is_string($format) ? $format : 'json');
        }

        return $contentTypes;
    }

    /**
     * @return array<string, string> transport name => DSN
     */
    private function transports(ContainerBuilder $container): array
    {
        $transports = [];

        // `messenger.receiver` is Messenger's own marker for transports; the
        // serializer/infrastructure services are not tagged with it.
        foreach ($container->findTaggedServiceIds(self::RECEIVER_TAG) as $id => $tags) {
            if ($this->isFailureTransport($tags)) {
                continue;
            }

            $dsn = $container->getDefinition($id)->getArguments()[0] ?? null;

            if (\is_string($dsn)) {
                $transports[$this->transportName($id, $tags)] = $dsn;
            }
        }

        return $transports;
    }

    /**
     * Messenger's failure/retry transport is never a public contract, so it is left
     * out of the documentation. Symfony marks it on the receiver tag.
     *
     * @param array<array-key, array<string, mixed>> $tags
     */
    private function isFailureTransport(array $tags): bool
    {
        foreach ($tags as $tag) {
            if (($tag['is_failure_transport'] ?? false) === true) {
                return true;
            }
        }

        return false;
    }

    /**
     * The transport name, taken from the tag's `alias` (falling back to the
     * service id).
     *
     * @param array<array-key, array<string, mixed>> $tags
     */
    private function transportName(string $id, array $tags): string
    {
        foreach ($tags as $tag) {
            if (isset($tag['alias']) && \is_string($tag['alias'])) {
                return $tag['alias'];
            }
        }

        return str_starts_with($id, self::TRANSPORT_PREFIX) ? substr($id, \strlen(self::TRANSPORT_PREFIX)) : $id;
    }

    /**
     * @return array<string, list<string>> message class/pattern => transport names
     */
    private function routing(ContainerBuilder $container): array
    {
        $map = $container->getDefinition('messenger.senders_locator')->getArgument(0);

        if (!\is_array($map)) {
            return [];
        }

        $routing = [];

        foreach ($map as $class => $transports) {
            if (\is_string($class) && \is_array($transports)) {
                $routing[$class] = array_values(array_filter($transports, 'is_string'));
            }
        }

        return $routing;
    }
}
