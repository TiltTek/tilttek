<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\MapHints;

#[CoversClass(MapHints::class)]
final class MapHintsTest extends TestCase
{
    /**
     * @return iterable<string, array{string, int|null, int|null, int|null}>
     */
    public static function names(): iterable
    {
        yield 'pixels and cells' => ['forest_30x20_140ppi.jpg', 140, 30, 20];
        yield 'brackets and dpi' => ['cave [25x30] 300dpi.png', 300, 25, 30];
        yield 'upper case' => ['TOWER_300DPI.PNG', 300, null, null];
        yield 'px' => ['crypt-70px.webp', 70, null, null];
        yield 'pxpi' => ['crypt 100pxpi.webp', 100, null, null];
        yield 'separated' => ['crypt_150 dpi.png', 150, null, null];
        yield 'dotted' => ['crypt.150.dpi.png', 150, null, null];
        yield 'multiplication sign' => ['keep 12×8.png', null, 12, 8];
        yield 'spaced' => ['keep 12 x 8.png', null, 12, 8];
        yield 'path' => ['/maps/2026/dungeon_40x40_100ppi/level-1.png', null, null, null];
        yield 'windows path' => ['C:\\maps\\cave_200dpi.jpg', 200, null, null];
        yield 'nothing' => ['tavern.png', null, null, null];
        yield 'too few pixels' => ['tavern_9dpi.png', null, null, null];
        yield 'below the minimum' => ['tavern_09dpi.png', null, null, null];
        yield 'at the minimum' => ['tavern_10dpi.png', 10, null, null];
        yield 'at the maximum' => ['tavern_2000dpi.png', 2000, null, null];
        yield 'above the maximum' => ['tavern_2001dpi.png', null, null, null];
        yield 'digits before' => ['tavern_12345dpi.png', null, null, null];
        yield 'a word after' => ['tavern_300pixels.png', null, null, null];
        yield 'image size' => ['tavern_1920x1080.png', null, null, null];
        yield 'zero cells' => ['tavern_0x10.png', null, null, null];
        yield 'zero rows' => ['tavern_10x0.png', null, null, null];
        yield 'invalid UTF-8 keeps the pixels' => ["tavern_\xff_10x10_100ppi.png", 100, null, null];
    }

    #[DataProvider('names')]
    public function testItReadsWhatTheNameSays(string $name, ?int $pixels, ?int $columns, ?int $rows): void
    {
        $hints = MapHints::fromFileName($name);

        self::assertSame($pixels, $hints->pixelsPerCell);
        self::assertSame($columns, $hints->columns);
        self::assertSame($rows, $hints->rows);
    }

    public function testATooLongNameGivesNoHint(): void
    {
        self::assertTrue(MapHints::fromFileName(str_repeat('a', 245).'_100dpi.png')->isEmpty());
        self::assertSame(100, MapHints::fromFileName(str_repeat('a', 244).'_100dpi.png')->pixelsPerCell);
    }

    public function testEmptiness(): void
    {
        self::assertTrue(MapHints::fromFileName('a.png')->isEmpty());
        self::assertFalse(MapHints::fromFileName('a_100dpi.png')->isEmpty());
        self::assertFalse(MapHints::fromFileName('a_10x10.png')->isEmpty());
    }

    public function testTheWidthOfACell(): void
    {
        self::assertSame('140', (string) MapHints::fromFileName('a_30x20_140ppi.png')->cellWidth(1000));
        self::assertSame('100', (string) MapHints::fromFileName('a_30x20.png')->cellWidth(3000));
        self::assertSame('1000/3', (string) MapHints::fromFileName('a_30x20.png')->cellWidth(10000));
        self::assertNull(MapHints::fromFileName('a.png')->cellWidth(1000));
        self::assertSame('1/30', (string) MapHints::fromFileName('a_30x20.png')->cellWidth(1));
    }

    public function testAnImageHasAWidth(): void
    {
        $this->expectException(OutOfBounds::class);
        $this->expectExceptionMessageIsOrContains('An image is at least one pixel wide.');

        MapHints::fromFileName('a_30x20.png')->cellWidth(0);
    }

    public function testADirectoryOfAWindowsPathIsLeftOut(): void
    {
        $hints = MapHints::fromFileName('C:\\maps_40x40_100ppi\\cave.jpg');

        self::assertNull($hints->pixelsPerCell);
        self::assertNull($hints->columns);
        self::assertNull($hints->rows);
    }
}
