<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\Exception\UnknownLoot;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootCatalog;
use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\LootTable;
use Tilttek\LootTables\Tests\Unit\Fixture\Loot;

#[CoversClass(LootCatalog::class)]
#[CoversClass(UnknownLoot::class)]
#[CoversClass(InvalidLoot::class)]
final class LootCatalogTest extends TestCase
{
    public function testTheTablesAndTheBoxesByKey(): void
    {
        $rings = Loot::table('rings', 6);
        $hats = Loot::table('hats', 20);
        $small = new LootBox('small', 'Small', [$rings]);
        $large = new LootBox('large', 'Large', [$rings, $hats]);
        $catalog = new LootCatalog([$rings, $hats], [$small, $large]);

        self::assertSame($rings, $catalog->table('rings'));
        self::assertSame($large, $catalog->box('large'));
        self::assertTrue($catalog->hasBox('small'));
        self::assertFalse($catalog->hasBox('rings'));
        self::assertSame([$rings, $hats], $catalog->tables());
        self::assertSame([$small, $large], $catalog->boxes());
    }

    public function testAnUnknownBox(): void
    {
        $this->expectException(UnknownLoot::class);
        $this->expectExceptionMessageIsOrContains('The catalogue holds no box "large".');

        new LootCatalog([], [])->box('large');
    }

    public function testAnUnknownTable(): void
    {
        $this->expectException(UnknownLoot::class);
        $this->expectExceptionMessageIsOrContains('The catalogue holds no table "rings".');

        new LootCatalog([], [])->table('rings');
    }

    public function testATableListedTwice(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The catalogue lists the table rings twice.');

        new LootCatalog([Loot::table('rings', 6), Loot::table('rings', 8)], []);
    }

    public function testABoxListedTwice(): void
    {
        $rings = Loot::table('rings', 6);

        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The catalogue lists the box small twice.');

        new LootCatalog([$rings], [new LootBox('small', 'Small', [$rings]), new LootBox('small', 'Small again', [$rings])]);
    }

    public function testABoxRollsOnlyTheTablesOfTheCatalogue(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The box small rolls the table rings, which is not one of the catalogue.');

        new LootCatalog([Loot::table('rings', 6)], [new LootBox('small', 'Small', [Loot::table('rings', 6)])]);
    }

    public function testABoxWhoseDrawsAreWrittenInAThousandCharactersAtMost(): void
    {
        // Ten tables write ten faces of one digit and 29 characters of separators; nine names of 96 characters and one
        // of 97 make 1000 characters.
        $base = new LootTable('base', 'Base', [new LootEntry('a', str_repeat('a', 96)), new LootEntry('b', 'B')]);
        $fits = new LootTable('fits', 'Fits', [new LootEntry('a', str_repeat('a', 97)), new LootEntry('b', 'B')]);
        $over = new LootTable('over', 'Over', [new LootEntry('a', str_repeat('é', 98)), new LootEntry('b', 'B')]);
        $catalog = new LootCatalog([$base, $fits, $over], [new LootBox('fits', 'Fits', [...array_fill(0, 9, $base), $fits])]);

        self::assertTrue($catalog->hasBox('fits'));

        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The draws of the box over may be written in 1001 characters; a draw is written in 1000 at most.');

        new LootCatalog([$base, $fits, $over], [new LootBox('over', 'Over', [...array_fill(0, 9, $base), $over])]);
    }
}
