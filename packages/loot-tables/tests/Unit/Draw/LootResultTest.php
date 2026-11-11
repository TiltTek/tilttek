<?php

declare(strict_types=1);

namespace Tilttek\LootTables\Tests\Unit\Draw;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Draw\DrawSource;
use Tilttek\LootTables\Draw\LootResult;
use Tilttek\LootTables\Draw\Pick;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\LootEntry;
use Tilttek\LootTables\Tests\Unit\Fixture\Loot;

#[CoversClass(LootResult::class)]
#[CoversClass(Pick::class)]
#[CoversClass(DrawSource::class)]
final class LootResultTest extends TestCase
{
    public function testTheEntriesOfTheFacesTheDiceShowed(): void
    {
        $box = Loot::box([6, 20, 8]);
        $result = LootResult::ofFaces($box, [3, 17, 8], DrawSource::Physical);

        self::assertSame($box, $result->box);
        self::assertSame(DrawSource::Physical, $result->source);
        self::assertSame([3, 17, 8], $result->faces());
        self::assertSame(['t1-3', 't2-17', 't3-8'], array_map(static fn (LootEntry $entry): string => $entry->key, $result->entries()));
        self::assertSame($box->tables[1], $result->picks[1]->table);
        self::assertSame(17, $result->picks[1]->face);
        self::assertSame($box->tables[1]->entry(17), $result->picks[1]->entry);
    }

    public function testOneFacePerTable(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The box box rolls 2 dice; 3 faces were given.');

        LootResult::ofFaces(Loot::box([6, 6]), [1, 2, 3], DrawSource::Verifiable);
    }

    public function testAFaceTheDieDoesNotHave(): void
    {
        $this->expectException(InvalidLoot::class);
        $this->expectExceptionMessageIsOrContains('The table t2 is rolled with a d6; 7 is not one of its faces.');

        LootResult::ofFaces(Loot::box([6, 6]), [1, 7], DrawSource::Verifiable);
    }

    public function testTheSources(): void
    {
        self::assertSame('verifiable', DrawSource::Verifiable->value);
        self::assertSame('physical', DrawSource::Physical->value);
    }
}
