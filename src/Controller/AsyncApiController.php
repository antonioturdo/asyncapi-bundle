<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;
use Zeusi\AsyncApiBundle\Generator\AsyncApiGenerator;

/**
 * Serves the generated AsyncAPI document as JSON and an HTML UI.
 *
 * The JSON endpoint is always available; the HTML UI is rendered with the
 * AsyncAPI web component when Twig is available.
 */
final class AsyncApiController
{
    /**
     * @param array<string, mixed> $uiConfig Opaque AsyncAPI web component config.
     */
    public function __construct(
        private readonly AsyncApiGenerator $generator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly ?Environment $twig = null,
        private readonly array $uiConfig = ['show' => ['sidebar' => true, 'errors' => true]],
        private readonly string $cssImportPath = 'https://unpkg.com/@asyncapi/react-component@3.1.3/styles/default.min.css',
    ) {}

    #[Route(path: '/asyncapi.json', name: 'asyncapi.document', methods: ['GET'])]
    public function document(): JsonResponse
    {
        return new JsonResponse($this->generator->generate()->toArray());
    }

    #[Route(path: '/asyncapi', name: 'asyncapi.ui', methods: ['GET'])]
    public function ui(): Response
    {
        if ($this->twig === null) {
            throw new \LogicException('The AsyncAPI UI requires symfony/twig-bundle. Install it, or use the /asyncapi.json endpoint.');
        }

        $html = $this->twig->render('@AsyncApi/ui.html.twig', [
            'schema_url' => $this->urlGenerator->generate('asyncapi.document'),
            'ui_config' => $this->uiConfig,
            'css_import_path' => $this->cssImportPath,
        ]);

        return new Response($html);
    }
}
