<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\LootBox;
use Tilttek\LootTables\LootTable;
use Tilttek\LootTables\Tests\Unit\Fixture\Loot;

#[CoversClass(LootBox::class)]
#[CoversClass(InvalidLoot::class)]
final class LootBoxTest extends TestCase
{
    public function testABoxRollsEachOfItsTablesWithItsOwnDie(): void
    {
        $headgear = Loot::table('headgear', 20);
        $rings = Loot::table('rings', 8);
        $box = new LootBox('mixed', 'Mixed box', [$headgear, $rings, $rings]);

        self::assertSame('mixed', $box->key);
        self::assertSame('Mixed box', $box->name);
        self::assertSame([$headgear, $rings, $rings], $box->tables);
        self::assertSame([20, 8, 8], $box->dice());
        self::assertSame('1d20, 1d8, 1d8', $box->pool()->canonical());
    }

    public function testABoxOfOneTable(): void
    {
        $table = LootTable::ofTexts('epitaphs', 'Epitaphs', ['Here lies a hero.', 'Gone fishing.']);
        $box = LootBox::single($table);

        self::assertSame('epitaphs', $box->key);
        self::assertSame('Epitaphs', $box->name);
        self::assertSame([$table], $box->tables);
        self::assertSame('1d2', $box->pool()->canonical());
    }

    public function testTenTablesAtMost(): void
    {
        self::assertCount(10, Loot::box(array_fill(0, 10, 6))->tables);

        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The box box has 11 tables; a box has 1 to 10.');

        Loot::box(array_fill(0, 11, 6));
    }

    public function testABoxWithoutATable(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The box empty has 0 tables; a box has 1 to 10.');

        new LootBox('empty', 'Empty', []);
    }

    public function testAKeyItRefuses(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('"A box" is not the key of a box: 1 to 64 lowercase letters, digits, "_" or "-".');

        new LootBox('A box', 'A box', [Loot::table('rings', 6)]);
    }

    public function testTheNameOfABoxIsTwoHundredCharactersAtMost(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The name of the box rings is 1 to 200 characters of UTF-8, not blank.');

        new LootBox('rings', str_repeat('é', 201), [Loot::table('rings', 6)]);
    }
}
