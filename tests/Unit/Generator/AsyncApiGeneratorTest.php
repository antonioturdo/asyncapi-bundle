<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Generator;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\AsyncApiDocument;
use Zeusi\AsyncApiBundle\Generator\AsyncApiGenerator;
use Zeusi\AsyncApiBundle\Processor\AsyncApiProcessorInterface;
use Zeusi\AsyncApiBundle\Processor\ConfigProcessor;
use Zeusi\AsyncApiBundle\Processor\DocumentContext;

final class AsyncApiGeneratorTest extends TestCase
{
    public function testItProducesAValidDocumentWhenNoProcessorsAreRegistered(): void
    {
        $generator = new AsyncApiGenerator([]);

        $document = $generator->generate('catalog');

        // The seeded base is already specification-valid: info defaults from the name.
        self::assertSame([
            'asyncapi' => '3.1.0',
            'info' => ['title' => 'catalog', 'version' => '1.0.0'],
        ], $document->toArray());
    }

    public function testConfigProcessorMergesStaticConfigAndProcessorsRunInOrder(): void
    {
        $trace = static function (string $name): AsyncApiProcessorInterface {
            return new class ($name) implements AsyncApiProcessorInterface {
                public function __construct(private readonly string $name) {}

                public function process(AsyncApiDocument $document, DocumentContext $context): void
                {
                    $existing = $document->extensions['x-trace'] ?? '';
                    $document->extensions['x-trace'] = (\is_string($existing) ? $existing : '') . $this->name;
                }
            };
        };

        $generator = new AsyncApiGenerator([
            new ConfigProcessor(['info' => ['title' => 'Acme']]),
            $trace('A'),
            $trace('B'),
        ]);

        $document = $generator->generate('ignored-seed')->toArray();

        // ConfigProcessor merged its static info over the seed (title wins, version kept).
        self::assertSame(['title' => 'Acme', 'version' => '1.0.0'], $document['info'] ?? null);
        // The two processors ran in registration order.
        self::assertSame('AB', $document['x-trace'] ?? null);
    }
}
