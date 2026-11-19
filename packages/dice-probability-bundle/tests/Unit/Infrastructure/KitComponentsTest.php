<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceProbabilityBundle\Infrastructure\Twig\KitComponents;

#[CoversClass(KitComponents::class)]
final class KitComponentsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, string}>
     */
    public static function subdirectories(): iterable
    {
        yield 'none' => ['', 'Card:Title'];
        yield 'one' => ['ui', 'ui:Card:Title'];
        yield 'nested' => ['ui/kit', 'ui:kit:Card:Title'];
        yield 'with slashes around' => ['/ui/', 'ui:Card:Title'];
        yield 'a slash alone' => ['/', 'Card:Title'];
    }

    #[DataProvider('subdirectories')]
    public function testAComponentIsNamedInItsSubdirectory(string $subdirectory, string $name): void
    {
        self::assertSame($name, new KitComponents($subdirectory)->name('Card:Title'));
    }

    public function testWithoutASubdirectoryTheNameIsTheComponent(): void
    {
        self::assertSame('Table', new KitComponents()->name('Table'));
    }

    public function testTheRecipesTheTemplatesDrawWith(): void
    {
        self::assertSame(['alert', 'card', 'chart', 'field', 'input', 'radio-group', 'table'], KitComponents::RECIPES);
    }
}
