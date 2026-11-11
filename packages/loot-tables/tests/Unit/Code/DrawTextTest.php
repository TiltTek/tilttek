<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Unit\Code;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Code\DrawText;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Exception\InvalidCode;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\LootTable;
use Tilttek\LootTables\Tests\Unit\Fixture\Loot;

#[CoversClass(DrawText::class)]
#[CoversClass(InvalidCode::class)]
final class DrawTextTest extends TestCase
{
    public function testTheFacesThenTheNames(): void
    {
        $box = Loot::box([6, 20, 100]);
        $text = DrawText::write(LootResult::ofFaces($box, [3, 17, 100], DrawSource::Verifiable));

        self::assertSame('3-17-100: Item 3, Item 17, Item 100', $text);
        self::assertSame([3, 17, 100], DrawText::read($box, $text, DrawSource::Verifiable)->faces());
        self::assertSame(DrawSource::Physical, DrawText::read($box, $text, DrawSource::Physical)->source);
    }

    public function testTenFacesOfUpToFourDigits(): void
    {
        $box = Loot::box([1000, 6, 6, 6, 6, 6, 6, 6, 6, 1000]);
        $text = DrawText::write(LootResult::ofFaces($box, [1000, 1, 2, 3, 4, 5, 6, 1, 2, 999], DrawSource::Verifiable));

        self::assertStringStartsWith('1000-1-2-3-4-5-6-1-2-999: ', $text);
        self::assertSame([1000, 1, 2, 3, 4, 5, 6, 1, 2, 999], DrawText::read($box, $text, DrawSource::Verifiable)->faces());
    }

    public function testABoxFitsWithinAThousandCharacters(): void
    {
        // Ten d2 tables write 39 characters of faces and separators; nine names of 96 characters and one of 97 or 98.
        $base = new LootTable('base', 'Base', [new LootEntry('a', str_repeat('a', 96)), new LootEntry('b', 'B')]);
        $fits = new LootBox('fits', 'Fits', [...array_fill(0, 9, $base), new LootTable('fits', 'Fits', [new LootEntry('a', str_repeat('a', 97)), new LootEntry('b', 'B')])]);
        $over = new LootBox('over', 'Over', [...array_fill(0, 9, $base), new LootTable('over', 'Over', [new LootEntry('a', str_repeat('a', 98)), new LootEntry('b', 'B')])]);

        self::assertSame(1000, DrawText::longest($fits));
        self::assertTrue(DrawText::fits($fits));
        self::assertSame(1001, DrawText::longest($over));
        self::assertFalse(DrawText::fits($over));
    }

    public function testReadingTakesTheFacesAlone(): void
    {
        self::assertSame([2, 5], DrawText::read(Loot::box([6, 6]), '2-5: renamed since: and more', DrawSource::Verifiable)->faces());
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refused(): iterable
    {
        yield 'no faces' => ['Sword, Helmet', 'A draw text starts with its faces joined by "-", then ": ".'];
        yield 'no colon' => ['2-5 Sword, Helmet', 'A draw text starts with its faces joined by "-", then ": ".'];
        yield 'a face 0' => ['0-5: Sword, Helmet', 'A draw text starts with its faces'];
        yield 'a face of five digits' => ['10000-5: Sword, Helmet', 'A draw text starts with its faces'];
        yield 'a space before' => [' 2-5: Sword, Helmet', 'A draw text starts with its faces'];
        yield 'too few faces' => ['2: Sword', 'A draw text of the box box has 2 faces, one per die.'];
        yield 'too many faces' => ['2-5-1: Sword, Helmet, Ring', 'A draw text of the box box has 2 faces, one per die.'];
        yield 'a face past the die' => ['2-7: Sword, Helmet', 'Face 2 of the draw text is not a face of a d6.'];
    }

    #[DataProvider('refused')]
    public function testADrawTextThatDoesNotRead(string $text, string $message): void
    {
        $this->expectException(InvalidCode::class);
        $this->expectExceptionMessageIsOrContains($message);

        DrawText::read(Loot::box([6, 6]), $text, DrawSource::Verifiable);
    }

    public function testElevenFacesNeverRead(): void
    {
        $this->expectException(InvalidCode::class);
        $this->expectExceptionMessageIsOrContains('A draw text starts with its faces joined by "-", then ": ".');

        DrawText::read(Loot::box([6]), '1-1-1-1-1-1-1-1-1-1-1: x', DrawSource::Verifiable);
    }

    public function testTheLongestLineOfABox(): void
    {
        $long = new LootTable('long', 'Long', [new LootEntry('a', 'Short'), new LootEntry('b', str_repeat('é', 12))]);
        $box = new LootBox('box', 'Box', [$long, Loot::table('d100', 100)]);

        // "2-100: " is 7 characters, the longest names 12 and "Item 100" 8, with ", " between them.
        self::assertSame(29, DrawText::longest($box));
        self::assertTrue(DrawText::fits($box));
        self::assertSame(1000, DrawText::MAX_LENGTH);
    }
}
