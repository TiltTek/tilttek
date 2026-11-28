<?php

declare(strict_types=1);

namespace Tilttek\Factions\Tests\Unit\Store;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Exception\RosterConflict;
use Tilttek\Factions\History\Entry;
use Tilttek\Factions\History\RankEntry;
use Tilttek\Factions\History\RelationEntry;
use Tilttek\Factions\History\StandingEntry;
use Tilttek\Factions\Reason;
use Tilttek\Factions\Relation;
use Tilttek\Factions\Roster;
use Tilttek\Factions\Scale;
use Tilttek\Factions\Stance;
use Tilttek\Factions\Store\InMemoryRosterStore;
use Tilttek\Factions\Subject;

#[CoversClass(InMemoryRosterStore::class)]
#[CoversClass(RosterConflict::class)]
#[CoversClass(StandingEntry::class)]
#[CoversClass(RankEntry::class)]
#[CoversClass(RelationEntry::class)]
final class InMemoryRosterStoreTest extends TestCase
{
    private function standing(string $faction, string $reason, string $campaign = 'c'): StandingEntry
    {
        return new StandingEntry($campaign, $faction, Subject::party(), 0, 1, new Reason($reason), 'm', new DateTimeImmutable('2026-11-25 10:00:00'));
    }

    public function testNothingIsKeptForACampaignNeverSaved(): void
    {
        $store = new InMemoryRosterStore();

        self::assertNull($store->roster('c'));
        self::assertSame([], [...$store->history('c', 'f', 10)]);
    }

    public function testANewRosterIsKeptAtVersionOneThenRaisedAtEachWrite(): void
    {
        $store = new InMemoryRosterStore();
        $kept = $store->save(new Roster('c', 'm', Scale::standard()));

        self::assertSame(1, $kept->version);
        self::assertSame($kept, $store->roster('c'));
        self::assertSame(2, $store->save($kept->withGameMaster('n'))->version);
        self::assertSame('n', $store->roster('c')->gameMaster);
    }

    public function testANewRosterOfAKeptCampaignIsRefused(): void
    {
        $store = new InMemoryRosterStore();
        $store->save(new Roster('c', 'm', Scale::standard()));

        $this->expectException(RosterConflict::class);
        $this->expectExceptionMessageIsOrContains('The roster of the campaign c changed since it was read.');

        $store->save(new Roster('c', 'n', Scale::standard()));
    }

    public function testAStaleRosterIsNotKept(): void
    {
        $store = new InMemoryRosterStore();
        $kept = $store->save(new Roster('c', 'm', Scale::standard()));
        $store->save($kept);

        try {
            $store->save($kept->withGameMaster('n'), [$this->standing('f', 'Lost')]);
            self::fail('Refused');
        } catch (RosterConflict) {
            self::assertSame('m', $store->roster('c')?->gameMaster);
            self::assertSame([], [...$store->history('c', 'f', 10)]);
        }
    }

    public function testARosterNeverKeptAtAVersionAboveZeroIsRefused(): void
    {
        $this->expectException(RosterConflict::class);

        new InMemoryRosterStore()->save(new Roster('c', 'm', Scale::standard(), version: 1));
    }

    public function testTheHistoryIsReadNewestFirstForOneFactionUpToTheLimit(): void
    {
        $store = new InMemoryRosterStore();
        $kept = $store->save(new Roster('c', 'm', Scale::standard()), [$this->standing('guild', 'one')]);
        $kept = $store->save($kept, [$this->standing('watch', 'two'), $this->standing('guild', 'three')]);

        $store->save(new Roster('d', 'm', Scale::standard()), [$this->standing('guild', 'other', 'd')]);
        $store->save($kept, [
            new RankEntry('c', 'guild', 'char-1', null, 'fence', new Reason('four'), 'm', new DateTimeImmutable()),
            new RelationEntry('c', 'cult', 'guild', null, new Relation('guild', 'cult', Stance::Allied), new Reason('five'), 'm', new DateTimeImmutable()),
        ]);

        $reasons = static fn (iterable $entries): array => array_map(static fn (Entry $entry): string => $entry->reason->text, [...$entries]);

        self::assertSame(['five', 'four', 'three', 'one'], $reasons($store->history('c', 'guild', 10)));
        self::assertSame(['five', 'four'], $reasons($store->history('c', 'guild', 2)));
        self::assertSame(['five'], $reasons($store->history('c', 'guild', 1)));
        self::assertSame(['two'], $reasons($store->history('c', 'watch', 10)));
        self::assertSame(['five'], $reasons($store->history('c', 'cult', 10)));
        self::assertSame(['other'], $reasons($store->history('d', 'guild', 10)));
    }

    public function testAReadOfTheHistoryTakesOneEntryAtLeast(): void
    {
        $this->expectException(InvalidRoster::class);
        $this->expectExceptionMessageIsOrContains('A store reads at least one entry at once.');

        new InMemoryRosterStore()->history('c', 'f', 0);
    }

    public function testARosterIsDeletedWithItsHistoryAtItsVersionOnly(): void
    {
        $store = new InMemoryRosterStore();
        $kept = $store->save(new Roster('c', 'm', Scale::standard()), [$this->standing('f', 'one')]);
        $store->save(new Roster('d', 'm', Scale::standard()), [$this->standing('f', 'kept', 'd')]);

        try {
            $store->delete($kept->withVersion(2));
            self::fail('Refused');
        } catch (RosterConflict) {
            self::assertInstanceOf(Roster::class, $store->roster('c'));
        }

        $store->delete($kept);

        self::assertNull($store->roster('c'));
        self::assertSame([], [...$store->history('c', 'f', 10)]);
        self::assertCount(1, [...$store->history('d', 'f', 10)]);
        self::assertSame(1, $store->save(new Roster('c', 'm', Scale::standard()))->version);
    }

    public function testARosterNeverKeptIsNotDeleted(): void
    {
        $this->expectException(RosterConflict::class);

        new InMemoryRosterStore()->delete(new Roster('c', 'm', Scale::standard()));
    }

    public function testEntriesSayWhichFactionsTheyConcern(): void
    {
        $at = new DateTimeImmutable('2026-11-25 10:00:00');
        $reason = new Reason('R', 's-1');
        $standing = new StandingEntry('c', 'guild', Subject::character('char-1'), -1, 2, $reason, 'm', $at);
        $rank = new RankEntry('c', 'guild', 'char-1', 'fence', null, $reason, 'm', $at);
        $relation = new RelationEntry('c', 'cult', 'guild', new Relation('cult', 'guild', Stance::Allied), null, $reason, 'm', $at);

        self::assertTrue($standing->concerns('guild'));
        self::assertFalse($standing->concerns('cult'));
        self::assertTrue($rank->concerns('guild'));
        self::assertFalse($rank->concerns('cult'));
        self::assertTrue($relation->concerns('guild'));
        self::assertTrue($relation->concerns('cult'));
        self::assertFalse($relation->concerns('watch'));
        self::assertSame([-1, 2, 'character:char-1', 'm', $at, $reason, 'c'], [$standing->before, $standing->after, $standing->subject->key, $standing->by, $standing->at, $standing->reason, $standing->campaign]);
        self::assertSame(['fence', null, 'char-1'], [$rank->before, $rank->after, $rank->character]);
        self::assertSame([Stance::Allied, null, 'guild'], [$relation->before?->stance, $relation->after, $relation->other]);
    }
}
