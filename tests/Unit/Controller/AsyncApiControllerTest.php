<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Controller;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Twig\Loader\FilesystemLoader;
use Zeusi\AsyncApiBundle\Controller\AsyncApiController;
use Zeusi\AsyncApiBundle\Generator\AsyncApiGenerator;
use Zeusi\AsyncApiBundle\Processor\ConfigProcessor;

final class AsyncApiControllerTest extends TestCase
{
    private const VIEWS_DIR = __DIR__ . '/../../../templates';

    public function testDocumentReturnsTheGeneratedDocumentAsJson(): void
    {
        $controller = new AsyncApiController(
            new AsyncApiGenerator([new ConfigProcessor(['info' => ['title' => 'Acme Events', 'version' => '2.0.0']])]),
            $this->urlGenerator(),
        );

        $response = $controller->document();
        $payload = json_decode((string) $response->getContent(), true);

        self::assertSame([
            'asyncapi' => '3.1.0',
            'info' => ['title' => 'Acme Events', 'version' => '2.0.0'],
        ], $payload);
    }

    public function testUiRendersTheWebComponentPointingAtTheJsonEndpoint(): void
    {
        $loader = new FilesystemLoader();
        $loader->addPath(self::VIEWS_DIR, 'AsyncApi');

        $controller = new AsyncApiController(
            new AsyncApiGenerator([]),
            $this->urlGenerator('/asyncapi.json'),
            new Environment($loader),
            ['show' => ['sidebar' => true, 'servers' => false]],
            'https://example.test/custom.css',
        );

        $html = (string) $controller->ui()->getContent();

        self::assertStringContainsString('<asyncapi-component', $html);
        self::assertStringContainsString('schemaUrl="/asyncapi.json"', $html);

        // The configured UI options reach the web component.
        self::assertStringContainsString('cssImportPath="https://example.test/custom.css"', $html);
        self::assertStringContainsString('servers', $html);
    }

    public function testUiFailsClearlyWhenTwigIsUnavailable(): void
    {
        $controller = new AsyncApiController(new AsyncApiGenerator([]), $this->urlGenerator());

        $this->expectException(\LogicException::class);
        $controller->ui();
    }

    private function urlGenerator(string $generated = '/asyncapi.json'): UrlGeneratorInterface
    {
        $urlGenerator = $this->createMock(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn($generated);

        return $urlGenerator;
    }
}
