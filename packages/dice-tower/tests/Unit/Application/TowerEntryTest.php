<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Application;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceTower\Application\TowerEntry;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;

#[CoversClass(TowerEntry::class)]
final class TowerEntryTest extends TestCase
{
    public function testAnEntryCarriesIdentifiersAndTheResultOnly(): void
    {
        $results = [
            ['expression' => '1d6', 'total' => 4, 'output' => 4, 'sets' => [['notation' => '1d6', 'die' => null, 'dice' => [['value' => 4, 'face' => null, 'kept' => true]]]]],
            ['expression' => '1d{coin}', 'total' => null, 'output' => ['heads'], 'sets' => []],
        ];
        $entry = new TowerEntry(12, 'table:7', 'abc', 'member:1', new DateTimeImmutable('2026-11-12 20:00:00+02:00'), $results);

        self::assertSame([4, null], $entry->totals());
        self::assertSame(
            ['position' => 12, 'table' => 'table:7', 'roll' => 'abc', 'roller' => 'member:1', 'rolled_at' => '2026-11-12T20:00:00+02:00', 'results' => $results],
            $entry->toArray(),
        );
    }

    public function testAnEntryIsMadeOfADropAndItsRoll(): void
    {
        $tower = new Tower();
        $roll = $tower->dice->rollReplayable('3d6');
        $drop = $tower->drops->append(Tower::table(), $roll->id(), Tower::member(), $tower->clock->now());
        $entry = TowerEntry::of($drop, $roll);

        self::assertSame([1, 'table:7', $roll->id(), 'member:1', $roll->rolledAt()], [$entry->position, $entry->table, $entry->roll, $entry->roller, $entry->rolledAt]);
        self::assertSame('3d6', $entry->results[0]['expression']);
        self::assertCount(3, $entry->results[0]['sets'][0]['dice']);
    }
}
