<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Loading\FileLoader;
use Tilttek\GeneratorBundle\Infrastructure\Twig\DrawExtension;

#[CoversClass(DrawExtension::class)]
final class DrawExtensionTest extends TestCase
{
    public function testATemplateDrawsResults(): void
    {
        $extension = new DrawExtension(new Generator(new FileLoader()->catalog([\dirname(__DIR__, 2).'/Fixture/generators'])));

        self::assertCount(1, $extension->draw('name.short')->results);
        $three = $extension->draw('name.short', 3, true, 'x');
        self::assertSame(3, $three->requested);
        self::assertCount(3, array_unique(array_filter($three->results, is_string(...))));
        self::assertEquals($three, $extension->draw('name.short', 3, true, 'x'));
        self::assertCount(2, $extension->draw('name.short', 2)->results);
        self::assertCount(9, $extension->draw('name.short', 9)->results);
    }
}
