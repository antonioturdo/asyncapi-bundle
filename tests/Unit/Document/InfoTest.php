<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Tests\Unit\Document;

use PHPUnit\Framework\TestCase;
use Zeusi\AsyncApiBundle\Document\Contact;
use Zeusi\AsyncApiBundle\Document\ExternalDocumentation;
use Zeusi\AsyncApiBundle\Document\Info;
use Zeusi\AsyncApiBundle\Document\License;
use Zeusi\AsyncApiBundle\Document\Tag;

final class InfoTest extends TestCase
{
    public function testItRendersOnlyTitleAndVersionByDefault(): void
    {
        $info = new Info(title: 'Wanderlust Events', version: '1.0.0');

        self::assertSame(['title' => 'Wanderlust Events', 'version' => '1.0.0'], $info->toArray());
    }

    public function testApplyArrayPartiallyOverridesAndKeepsExtensions(): void
    {
        // A seeded Info, as the generator would create it.
        $info = new Info(title: 'seed-name', version: '1.0.0');

        $info->applyArray([
            'title' => 'Wanderlust Events',
            // version omitted on purpose — the seeded one must survive.
            'externalDocs' => ['url' => 'https://docs.wanderlust.example'],
            'x-audience' => 'public',
        ]);

        self::assertSame('Wanderlust Events', $info->title);
        self::assertSame('1.0.0', $info->version);
        self::assertSame('public', $info->extensions['x-audience']);

        self::assertSame([
            'title' => 'Wanderlust Events',
            'version' => '1.0.0',
            'externalDocs' => ['url' => 'https://docs.wanderlust.example'],
            'x-audience' => 'public',
        ], $info->toArray());
    }

    public function testItRendersTheFullCalmInfoObject(): void
    {
        $info = new Info(
            title: 'Wanderlust Events',
            version: '1.0.0',
            description: 'Events Wanderlust publishes.',
            termsOfService: 'https://wanderlust.example/terms',
            contact: new Contact(name: 'API Team', url: 'https://wanderlust.example', email: 'api@wanderlust.example'),
            license: new License(name: 'MIT', identifier: 'MIT'),
            externalDocs: new ExternalDocumentation(url: 'https://docs.wanderlust.example', description: 'Full docs'),
        );
        $info->tags = [new Tag(name: 'travel', description: 'Travel events')];

        self::assertSame(
            [
                'title' => 'Wanderlust Events',
                'version' => '1.0.0',
                'description' => 'Events Wanderlust publishes.',
                'termsOfService' => 'https://wanderlust.example/terms',
                'contact' => ['name' => 'API Team', 'url' => 'https://wanderlust.example', 'email' => 'api@wanderlust.example'],
                'license' => ['name' => 'MIT', 'identifier' => 'MIT'],
                'tags' => [['name' => 'travel', 'description' => 'Travel events']],
                'externalDocs' => ['url' => 'https://docs.wanderlust.example', 'description' => 'Full docs'],
            ],
            $info->toArray(),
        );
    }
}
