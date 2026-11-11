<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\LootTable;
use Tilttek\LootTables\Tests\Unit\Fixture\Loot;

#[CoversClass(LootTable::class)]
#[CoversClass(InvalidLoot::class)]
final class LootTableTest extends TestCase
{
    public function testATableIsRolledWithADieOfOneSidePerEntry(): void
    {
        $table = new LootTable('headgear', 'Headgear', [new LootEntry('helmet', 'Helmet'), new LootEntry('hood', 'Hood')]);

        self::assertSame('headgear', $table->key);
        self::assertSame('Headgear', $table->name);
        self::assertSame(2, $table->sides());
        self::assertSame(['helmet', 'hood'], array_map(static fn (LootEntry $entry): string => $entry->key, $table->entries));
        self::assertSame('helmet', $table->entry(1)->key);
        self::assertSame('hood', $table->entry(2)->key);
    }

    public function testATableOfAThousandEntries(): void
    {
        self::assertSame(1000, Loot::table('big', 1000)->sides());
    }

    public function testATableOfTexts(): void
    {
        $table = LootTable::ofTexts('epitaphs', 'Epitaphs', ['Here lies a hero.', 'Gone fishing.', 'Back soon.']);

        self::assertSame(3, $table->sides());
        self::assertSame(['1', '2', '3'], array_map(static fn (LootEntry $entry): string => $entry->key, $table->entries));
        self::assertSame('Gone fishing.', $table->entry(2)->name);
    }

    public function testAFaceTheDieDoesNotHave(): void
    {
        $table = Loot::table('rings', 6);

        try {
            $table->entry(0);
            self::fail('Face 0 was read.');
        } catch (InvalidLoot $invalidLoot) {
            self::assertSame('The table rings is rolled with a d6; 0 is not one of its faces.', $invalidLoot->getMessage());
        }

        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The table rings is rolled with a d6; 7 is not one of its faces.');

        $table->entry(7);
    }

    public function testOneEntryIsNotATable(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The table one has 1 entries; a table has 2 to 1000, one per side of its die.');

        new LootTable('one', 'One', [new LootEntry('a', 'A')]);
    }

    public function testATableOfMoreThanAThousandEntries(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The table big has 1001 entries; a table has 2 to 1000');

        Loot::table('big', 1001);
    }

    public function testAnEntryListedTwice(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The table rings lists the entry ring twice.');

        new LootTable('rings', 'Rings', [new LootEntry('ring', 'Ring'), new LootEntry('band', 'Band'), new LootEntry('ring', 'Ring again')]);
    }

    public function testAKeyItRefuses(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('"Rings" is not the key of a table: 1 to 64 lowercase letters, digits, "_" or "-".');

        new LootTable('Rings', 'Rings', [new LootEntry('a', 'A'), new LootEntry('b', 'B')]);
    }

    public function testTheNameOfATableIsTwoHundredCharactersAtMost(): void
    {
        self::assertSame(200, mb_strlen(new LootTable('rings', str_repeat('é', 200), [new LootEntry('a', 'A'), new LootEntry('b', 'B')])->name));

        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The name of the table rings is 1 to 200 characters of UTF-8, not blank.');

        new LootTable('rings', str_repeat('é', 201), [new LootEntry('a', 'A'), new LootEntry('b', 'B')]);
    }
}
