<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\DependencyInjection;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;
use Zeusi\AsyncApiBundle\AsyncApiBundle;
use Zeusi\JsonSchemaExtractor\Enricher\SymfonyValidationEnricher;
use Zeusi\JsonSchemaExtractor\Serialization\SymfonySerializerStrategy;

/**
 * Upgrades the built-in default SchemaExtractor with the Symfony integrations.
 *
 * They depend on framework services — only known to exist after every extension
 * has loaded, hence a compiler pass. When the Symfony Serializer is available,
 * its strategy replaces the json_encode fallback (so groups, name converters,
 * discriminators, etc. are honoured); when the Validator is available, its
 * enricher is added (so constraints reach the schema).
 */
final class DefaultExtractorPass implements CompilerPassInterface
{
    private const SERIALIZER_STRATEGY = 'asyncapi.json_schema.serialization.symfony_serializer';
    private const VALIDATOR_ENRICHER = 'asyncapi.json_schema.enricher.validator';

    private const SERIALIZER_METADATA_FACTORY = 'serializer.mapping.class_metadata_factory';
    private const SERIALIZER_NAME_CONVERTER = 'serializer.name_converter';
    private const VALIDATOR_METADATA_FACTORY = 'validator.mapping.class_metadata_factory';

    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(AsyncApiBundle::PAYLOAD_SCHEMA_EXTRACTOR_SERVICE)) {
            return;
        }

        $this->preferSymfonySerializer($container);
        $this->addValidationEnricher($container);
    }

    private function preferSymfonySerializer(ContainerBuilder $container): void
    {
        if (!$container->has(self::SERIALIZER_METADATA_FACTORY)) {
            return;
        }

        $strategy = (new Definition(SymfonySerializerStrategy::class))
            ->setArgument('$classMetadataFactory', new Reference(self::SERIALIZER_METADATA_FACTORY));

        if ($container->has(self::SERIALIZER_NAME_CONVERTER)) {
            $strategy->setArgument('$nameConverter', new Reference(self::SERIALIZER_NAME_CONVERTER));
        }

        $container->setDefinition(self::SERIALIZER_STRATEGY, $strategy);

        $container->getDefinition(AsyncApiBundle::PAYLOAD_SCHEMA_EXTRACTOR_SERVICE)
            ->setArgument('$serializationStrategy', new Reference(self::SERIALIZER_STRATEGY));
    }

    private function addValidationEnricher(ContainerBuilder $container): void
    {
        if (!$container->has(self::VALIDATOR_METADATA_FACTORY)) {
            return;
        }

        $container->setDefinition(
            self::VALIDATOR_ENRICHER,
            (new Definition(SymfonyValidationEnricher::class))
                ->setArgument('$metadataFactory', new Reference(self::VALIDATOR_METADATA_FACTORY))
                ->addTag(AsyncApiBundle::TAG_JSON_SCHEMA_ENRICHER),
        );
    }
}
