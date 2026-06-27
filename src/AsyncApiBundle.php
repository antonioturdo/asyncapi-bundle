<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle;

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Configurator\DefinitionConfigurator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\service;

use Symfony\Component\DependencyInjection\Loader\Configurator\ServicesConfigurator;

use function Symfony\Component\DependencyInjection\Loader\Configurator\tagged_iterator;

use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Zeusi\AsyncApiBundle\Controller\AsyncApiController;
use Zeusi\AsyncApiBundle\DependencyInjection\DefaultExtractorPass;
use Zeusi\AsyncApiBundle\DependencyInjection\MessageDiscoveryPass;
use Zeusi\AsyncApiBundle\DependencyInjection\MessengerWiringPass;
use Zeusi\AsyncApiBundle\Discovery\AttributeMessageProvider;
use Zeusi\AsyncApiBundle\Discovery\MessageProviderInterface;
use Zeusi\AsyncApiBundle\Generator\AsyncApiGenerator;
use Zeusi\AsyncApiBundle\Processor\AsyncApiProcessorInterface;
use Zeusi\AsyncApiBundle\Processor\CanonicalizeMessagesProcessor;
use Zeusi\AsyncApiBundle\Processor\ConfigProcessor;
use Zeusi\AsyncApiBundle\Processor\DiscoveryProcessor;
use Zeusi\AsyncApiBundle\Processor\PayloadProcessor;
use Zeusi\AsyncApiBundle\Processor\VersionValidationProcessor;
use Zeusi\JsonSchemaExtractor\Discoverer\ReflectionDiscoverer;
use Zeusi\JsonSchemaExtractor\Enricher\PhpDocumentorEnricher;
use Zeusi\JsonSchemaExtractor\Enricher\PhpStanEnricher;
use Zeusi\JsonSchemaExtractor\Mapper\StandardJsonSchemaMapper;
use Zeusi\JsonSchemaExtractor\SchemaExtractor;
use Zeusi\JsonSchemaExtractor\Serialization\JsonEncodeSerializationStrategy;

final class AsyncApiBundle extends AbstractBundle
{
    protected string $extensionAlias = 'asyncapi';

    public const PAYLOAD_SCHEMA_EXTRACTOR_SERVICE = 'asyncapi.payload_schema_extractor';
    public const TAG_JSON_SCHEMA_ENRICHER = 'asyncapi.json_schema_enricher';
    public const PARAM_DISCOVERY_ATTRIBUTE_PATHS = 'asyncapi.discovery.attribute.paths';
    public const PARAM_MESSENGER_ENRICHMENT = 'asyncapi.messenger.enrichment';
    public const PARAM_MESSENGER_TRANSPORTS = 'asyncapi.messenger.transports';
    public const PARAM_MESSENGER_DISCOVERY = 'asyncapi.messenger.discovery';
    public const TAG_PROCESSOR = 'asyncapi.processor';
    public const TAG_MESSAGE_PROVIDER = 'asyncapi.message_provider';
    private const SERVICE_DISCOVERER = 'asyncapi.json_schema.discoverer';
    private const SERVICE_MAPPER = 'asyncapi.json_schema.mapper';
    private const SERVICE_JSON_ENCODE = 'asyncapi.json_schema.serialization.json_encode';
    private const SERVICE_PHPDOC_ENRICHER = 'asyncapi.json_schema.enricher.phpdoc';

    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        // Any service implementing these interfaces is tagged automatically.
        $container
            ->registerForAutoconfiguration(AsyncApiProcessorInterface::class)
            ->addTag(self::TAG_PROCESSOR);
        $container
            ->registerForAutoconfiguration(MessageProviderInterface::class)
            ->addTag(self::TAG_MESSAGE_PROVIDER);

        // Scans for #[AsyncApiMessage] classes and feeds the attribute provider.
        $container->addCompilerPass(new MessageDiscoveryPass());

        // Upgrades the default extractor with the Symfony serializer/validator
        // integrations once their services are known to exist.
        $container->addCompilerPass(new DefaultExtractorPass());

        // Adds servers derived from Messenger transports, when Messenger is present.
        $container->addCompilerPass(new MessengerWiringPass());
    }

    public function configure(DefinitionConfigurator $definition): void
    {
        $children = $this->rootNodeChildren($definition->rootNode());

        $children
            ->variableNode('document')
            ->defaultValue([])
            ->info('Static AsyncAPI fragment (asyncapi version, info, servers, security, tags…) merged as the base of the generated document. The `asyncapi` version defaults to the latest supported and is validated.');

        $extractor = $children->arrayNode('payload_schema_extractor')->addDefaultsIfNotSet();
        // Shorthand: a bare string is the extractor service id.
        $extractor
            ->beforeNormalization()
            ->ifString()
            ->then(static fn(string $service): array => ['service' => $service])
            ->end();
        $extractorChildren = $extractor->children();
        $extractorChildren
            ->scalarNode('service')
            ->defaultValue(self::PAYLOAD_SCHEMA_EXTRACTOR_SERVICE)
            ->cannotBeEmpty()
            ->info('Service id of the json-schema-extractor SchemaExtractor used to derive payloads. Defaults to a built-in one (Symfony Serializer when available, else json_encode).');
        $extractorChildren
            ->scalarNode('context_factory')
            ->defaultNull()
            ->info('Optional service id implementing Payload\ExtractionContextFactory, building the ExtractionContext (e.g. serialization groups) passed to the extractor per message.');

        $providers = $children->arrayNode('providers')->addDefaultsIfNotSet()->children();

        $attribute = $providers->arrayNode('attribute')->addDefaultsIfNotSet()->children();
        $attribute
            ->arrayNode('paths')
            ->info('Directories scanned for classes marked with #[AsyncApiMessage]. Defaults to the project PSR-4 roots when omitted.')
            ->scalarPrototype()
            ->end();

        $messenger = $providers->arrayNode('messenger')->addDefaultsIfNotSet()->children();
        $messenger
            ->arrayNode('transports')
            ->info('Allowlist of transport names the integration considers; empty means all. Failure transports are always excluded. Scopes both enrichment and discovery.')
            ->scalarPrototype()
            ->end();
        $messenger
            ->booleanNode('enrichment')
            ->defaultFalse()
            ->info('Enrich documented messages from your transports: servers (from the DSN), content type (from the serializer) and channel↔server links. Off by default — enable when your Messenger transports are your publication channel.');
        $messenger
            ->booleanNode('discovery')
            ->defaultFalse()
            ->info('Discover messages from the Messenger routing map (FQCN keys plus interface/parent/namespace-wildcard matches), scoped by `transports`. Off by default.');

        $ui = $children->arrayNode('ui')->addDefaultsIfNotSet()->children();
        $ui
            ->variableNode('config')
            ->defaultValue(['show' => ['sidebar' => true, 'errors' => true]])
            ->info('Opaque AsyncAPI web component config, emitted verbatim as its `config` attribute.');
        $ui
            ->scalarNode('css_import_path')
            ->defaultValue('https://unpkg.com/@asyncapi/react-component@3.1.3/styles/default.min.css')
            ->cannotBeEmpty()
            ->info('Stylesheet URL the AsyncAPI web component loads (override for a custom theme).');
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        // Register the bundle's template path only when Twig is available, so the
        // UI is an opt-in feature and Twig stays an optional dependency.
        if (!$builder->hasExtension('twig')) {
            return;
        }

        $container->extension('twig', [
            'paths' => [$this->getPath() . '/templates' => 'AsyncApi'],
        ]);
    }

    /**
     * @param array{
     *     document: array<array-key, mixed>,
     *     payload_schema_extractor: array{service: string, context_factory: ?string},
     *     providers: array{
     *         attribute: array{paths: list<string>},
     *         messenger: array{transports: list<string>, enrichment: bool, discovery: bool}
     *     },
     *     ui: array{config: array<string, mixed>, css_import_path: string}
     * } $config
     */
    public function loadExtension(array $config, ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->setParameter(self::PARAM_DISCOVERY_ATTRIBUTE_PATHS, $config['providers']['attribute']['paths']);
        $builder->setParameter(self::PARAM_MESSENGER_ENRICHMENT, $config['providers']['messenger']['enrichment']);
        $builder->setParameter(self::PARAM_MESSENGER_TRANSPORTS, $config['providers']['messenger']['transports']);
        $builder->setParameter(self::PARAM_MESSENGER_DISCOVERY, $config['providers']['messenger']['discovery']);

        $services = $container->services();
        $services->defaults()->autowire()->autoconfigure();

        // Built-in processor: merges the static config fragment into the base. Runs first.
        $services
            ->set(ConfigProcessor::class)
            ->autowire(false)
            ->autoconfigure(false)
            ->arg('$document', $config['document'])
            ->tag(self::TAG_PROCESSOR, ['priority' => 1000]);

        // Candidate classes are filled in at compile time by MessageDiscoveryPass;
        // the message-provider tag is added by autoconfiguration.
        $services
            ->set(AttributeMessageProvider::class)
            ->arg('$classes', []);

        // Assembles channels/operations/messages from all providers. Explicit
        // priority (not the autoconfigured default 0), so a user processor left at
        // the default 0 lands in a clean slot: after discovery, before payload.
        $services
            ->set(DiscoveryProcessor::class)
            ->autowire(false)
            ->autoconfigure(false)
            ->arg('$providers', tagged_iterator(self::TAG_MESSAGE_PROVIDER))
            ->arg('$logger', service('logger')->ignoreOnInvalid())
            ->tag(self::TAG_PROCESSOR, ['priority' => 500]);

        // Derives payload schemas via json-schema-extractor. Runs last (after
        // discovery); the extractor is optional and injected only when present.
        // The context factory is optional too — wired only when configured.
        $payloadProcessor = $services
            ->set(PayloadProcessor::class)
            ->autowire(false)
            ->autoconfigure(false)
            ->arg('$extractor', service($config['payload_schema_extractor']['service'])->ignoreOnInvalid())
            ->arg('$logger', service('logger')->ignoreOnInvalid())
            ->tag(self::TAG_PROCESSOR, ['priority' => -100]);

        if ($config['payload_schema_extractor']['context_factory'] !== null) {
            $payloadProcessor->arg('$contextFactory', service($config['payload_schema_extractor']['context_factory']));
        }

        // Hoists inline channel messages into components.messages (after payloads
        // are filled). Opinionated canonicalization — drop it for inline messages.
        $services
            ->set(CanonicalizeMessagesProcessor::class)
            ->autowire(false)
            ->autoconfigure(false)
            ->tag(self::TAG_PROCESSOR, ['priority' => -500]);

        // Validates the targeted AsyncAPI version once everything is merged. Runs last.
        $services
            ->set(VersionValidationProcessor::class)
            ->autowire(false)
            ->autoconfigure(false)
            ->tag(self::TAG_PROCESSOR, ['priority' => -1000]);

        $this->registerDefaultExtractor($services);

        $services
            ->set(AsyncApiGenerator::class)
            ->arg('$processors', tagged_iterator(self::TAG_PROCESSOR))
            ->public();

        // Twig is optional: inject null when the service is absent, so only the
        // JSON endpoint is available without symfony/twig-bundle.
        $services
            ->set(AsyncApiController::class)
            ->arg('$twig', service('twig')->ignoreOnInvalid())
            ->arg('$uiConfig', $config['ui']['config'])
            ->arg('$cssImportPath', $config['ui']['css_import_path'])
            ->tag('controller.service_arguments')
            ->public();
    }

    private function registerDefaultExtractor(ServicesConfigurator $services): void
    {
        $services
            ->set(self::SERVICE_DISCOVERER, ReflectionDiscoverer::class)
            ->autowire(false)
            ->autoconfigure(false);

        $services
            ->set(self::SERVICE_MAPPER, StandardJsonSchemaMapper::class)
            ->autowire(false)
            ->autoconfigure(false);

        $services
            ->set(self::SERVICE_JSON_ENCODE, JsonEncodeSerializationStrategy::class)
            ->autowire(false)
            ->autoconfigure(false);

        $phpDocEnricher = $this->phpDocEnricher();

        if ($phpDocEnricher !== null) {
            $services
                ->set(self::SERVICE_PHPDOC_ENRICHER, $phpDocEnricher)
                ->autowire(false)
                ->autoconfigure(false)
                ->tag(self::TAG_JSON_SCHEMA_ENRICHER);
        }

        // The Symfony serializer strategy and validator enricher (which depend on
        // framework services) are layered in by DefaultExtractorPass at compile time.
        $services
            ->set(self::PAYLOAD_SCHEMA_EXTRACTOR_SERVICE, SchemaExtractor::class)
            ->autowire(false)
            ->autoconfigure(false)
            ->public()
            ->arg('$discoverer', service(self::SERVICE_DISCOVERER))
            ->arg('$enrichers', tagged_iterator(self::TAG_JSON_SCHEMA_ENRICHER))
            ->arg('$serializationStrategy', service(self::SERVICE_JSON_ENCODE))
            ->arg('$mapper', service(self::SERVICE_MAPPER));
    }

    /**
     * @return class-string<PhpStanEnricher|PhpDocumentorEnricher>|null
     */
    private function phpDocEnricher(): ?string
    {
        if (class_exists('PHPStan\\PhpDocParser\\ParserConfig')) {
            return PhpStanEnricher::class;
        }

        if (class_exists('phpDocumentor\\Reflection\\DocBlockFactory')) {
            return PhpDocumentorEnricher::class;
        }

        return null;
    }

    private function rootNodeChildren(object $rootNode): NodeBuilder
    {
        if (!method_exists($rootNode, 'children')) {
            throw new \LogicException('The asyncapi root configuration node must support children.');
        }

        return $rootNode->children();
    }
}
