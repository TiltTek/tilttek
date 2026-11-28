<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Tests\Unit\Store;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\Exception\PlayConflict;
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\PlayQuery;
use Tilttek\PlayLog\PlayQueryKind;
use Tilttek\PlayLog\Seat;
use Tilttek\PlayLog\Store\InMemoryPlayStore;

#[CoversClass(InMemoryPlayStore::class)]
#[CoversClass(PlayQuery::class)]
#[CoversClass(PlayQueryKind::class)]
#[CoversClass(PlayConflict::class)]
final class InMemoryPlayStoreTest extends TestCase
{
    public function testAPlayIsKeptAtTheNextVersion(): void
    {
        self::assertNull(new InMemoryPlayStore()->play('p1'));
        $store = new InMemoryPlayStore();
        self::assertSame(1, $store->save($this->play('p1'))->version);
        self::assertSame(1, $store->play('p1')?->version);
        self::assertSame(2, $store->save($this->play('p1', version: 1))->version);
    }

    public function testANewPlayIsKeptOnce(): void
    {
        $store = new InMemoryPlayStore();
        $store->save($this->play('p1'));

        $this->expectException(PlayConflict::class);
        $this->expectExceptionMessageIsOrContains('The play p1 changed since it was read.');

        $store->save($this->play('p1'));
    }

    public function testAStalePlayIsRefused(): void
    {
        $store = new InMemoryPlayStore();
        $store->save($this->play('p1'));
        $store->save($this->play('p1', version: 1));

        $this->expectException(PlayConflict::class);

        $store->save($this->play('p1', version: 1));
    }

    public function testAPlayNeverKeptIsNotSavedAtAVersion(): void
    {
        $this->expectException(PlayConflict::class);

        new InMemoryPlayStore()->save($this->play('p1', version: 1));
    }

    public function testAPlayIsDeletedAtItsVersion(): void
    {
        $store = new InMemoryPlayStore();
        $store->save($this->play('p1'));
        $store->delete($this->play('p1', version: 1));

        self::assertNull($store->play('p1'));
    }

    public function testAStaleDeletionIsRefused(): void
    {
        $store = new InMemoryPlayStore();
        $store->save($this->play('p1'));
        $store->save($this->play('p1', version: 1));

        try {
            $store->delete($this->play('p1', version: 1));
            self::fail('A stale play is deleted.');
        } catch (PlayConflict) {
            self::assertSame(2, $store->play('p1')?->version);
        }
    }

    public function testAPlayNeverKeptIsNotDeletedWhateverItsVersion(): void
    {
        $store = new InMemoryPlayStore();

        foreach ([0, 1, 2] as $version) {
            try {
                $store->delete($this->play('p1', version: $version));
                self::fail('A play never kept is deleted.');
            } catch (PlayConflict $conflict) {
                self::assertStringContainsString('p1', $conflict->getMessage());
            }
        }
    }

    public function testAPlayNeverKeptIsNotDeleted(): void
    {
        $this->expectException(PlayConflict::class);

        new InMemoryPlayStore()->delete($this->play('p1'));
    }

    public function testThePlaysComeLatestFirstUndatedLast(): void
    {
        $store = new InMemoryPlayStore();

        foreach ([['d', null], ['b', '2027-01-02'], ['a', '2027-01-01'], ['c', '2027-01-02'], ['e', null], ['f', '2027-01-03']] as [$id, $date]) {
            $store->save($this->play($id, null === $date ? null : new DateTimeImmutable($date)));
        }

        self::assertSame(['f', 'b', 'c', 'a', 'd', 'e'], $this->ids($store->plays(PlayQuery::recordedBy('ana'), 10)));
        self::assertSame(['f', 'b'], $this->ids($store->plays(PlayQuery::recordedBy('ana'), 2)));
        self::assertSame(['f'], $this->ids($store->plays(PlayQuery::recordedBy('ana'), 1)));
    }

    public function testAStoreReadsAtLeastOnePlay(): void
    {
        $this->expectException(InvalidPlay::class);
        $this->expectExceptionMessageIsOrContains('A store reads at least one play at once.');

        new InMemoryPlayStore()->plays(PlayQuery::recordedBy('ana'), 0);
    }

    public function testTheUndatedComeLastWhicheverIsFirst(): void
    {
        $store = new InMemoryPlayStore();
        $store->save($this->play('a'));
        $store->save($this->play('b', new DateTimeImmutable('2027-01-01')));
        $store->save($this->play('c'));
        $store->save($this->play('d', new DateTimeImmutable('2026-01-01')));

        self::assertSame(['b', 'd', 'a', 'c'], $this->ids($store->plays(PlayQuery::recordedBy('ana'), 10)));
    }

    public function testAQueryReadsItsSubject(): void
    {
        $store = new InMemoryPlayStore();
        $store->save(new Play('p1', 'tide', 'ana', [new Seat('ana', confirmed: true), new Seat('bo')], group: 'club'));
        $store->save(new Play('p2', 'ebb', 'bo', [new Seat('bo', confirmed: true), new Seat('ana', confirmed: true)]));
        $store->save(new Play('p3', 'tide', 'cy', [new Seat('cy', confirmed: true)], group: 'club'));

        self::assertSame(['p1', 'p2'], $this->ids($store->plays(PlayQuery::ofMember('ana'), 10)));
        self::assertSame(['p2'], $this->ids($store->plays(PlayQuery::ofMember('bo'), 10)));
        self::assertSame(['p1'], $this->ids($store->plays(PlayQuery::pendingFor('bo'), 10)));
        self::assertSame([], $this->ids($store->plays(PlayQuery::pendingFor('ana'), 10)));
        self::assertSame([], $this->ids($store->plays(PlayQuery::pendingFor('dee'), 10)));
        self::assertSame(['p2'], $this->ids($store->plays(PlayQuery::recordedBy('bo'), 10)));
        self::assertSame(['p1', 'p3'], $this->ids($store->plays(PlayQuery::ofGroup('club'), 10)));
        self::assertSame(['p1', 'p3'], $this->ids($store->plays(PlayQuery::ofGroup('club')->forGame('tide'), 10)));
        self::assertSame(['p2'], $this->ids($store->plays(PlayQuery::ofMember('ana')->forGame('ebb'), 10)));
    }

    public function testAPeriodIncludesItsStartExcludesItsEndAndTheUndated(): void
    {
        $store = new InMemoryPlayStore();

        foreach (['a' => '2027-01-01', 'b' => '2027-01-02', 'c' => '2027-01-03', 'd' => null] as $id => $date) {
            $store->save($this->play($id, null === $date ? null : new DateTimeImmutable($date)));
        }

        $query = PlayQuery::recordedBy('ana');

        self::assertSame(['b'], $this->ids($store->plays($query->between(new DateTimeImmutable('2027-01-02'), new DateTimeImmutable('2027-01-03')), 10)));
        self::assertSame(['c', 'b'], $this->ids($store->plays($query->between(new DateTimeImmutable('2027-01-02'), null), 10)));
        self::assertSame(['a'], $this->ids($store->plays($query->between(null, new DateTimeImmutable('2027-01-02')), 10)));
        self::assertSame(['c', 'b', 'a', 'd'], $this->ids($store->plays($query->between(null, null), 10)));
        self::assertSame([], $this->ids($store->plays($query->between(new DateTimeImmutable('2027-01-02'), new DateTimeImmutable('2027-01-02')), 10)));
    }

    public function testAQueryKeepsWhatItIsGiven(): void
    {
        $from = new DateTimeImmutable('2027-01-01');
        $until = new DateTimeImmutable('2027-02-01');
        $query = PlayQuery::ofGroup('club')->forGame('tide')->between($from, $until)->forGame('ebb');

        self::assertSame([PlayQueryKind::Group, 'club', 'ebb', $from, $until], [$query->kind, $query->subject, $query->game, $query->from, $query->until]);
        self::assertSame([PlayQueryKind::Member, 'ana'], [PlayQuery::ofMember('ana')->kind, PlayQuery::ofMember('ana')->subject]);
        self::assertSame(PlayQueryKind::Pending, PlayQuery::pendingFor('ana')->kind);
        self::assertSame(PlayQueryKind::RecordedBy, PlayQuery::recordedBy('ana')->kind);
        self::assertSame('ebb', PlayQuery::ofMember('ana')->forGame('tide')->between($from, null)->forGame('ebb')->game);
        self::assertSame($from, PlayQuery::ofMember('ana')->between($from, null)->forGame('ebb')->from);
    }

    public function testAPeriodEndsAfterItStarts(): void
    {
        $this->expectException(InvalidPlay::class);
        $this->expectExceptionMessageIsOrContains('A period ends after it starts.');

        PlayQuery::ofMember('ana')->between(new DateTimeImmutable('2027-01-02'), new DateTimeImmutable('2027-01-01'));
    }

    /**
     * @return iterable<string, array{callable(): PlayQuery}>
     */
    public static function queriesOutOfShape(): iterable
    {
        yield 'a member' => [static fn (): PlayQuery => PlayQuery::ofMember('a b')];
        yield 'a pending member' => [static fn (): PlayQuery => PlayQuery::pendingFor('a b')];
        yield 'a recorder' => [static fn (): PlayQuery => PlayQuery::recordedBy('a b')];
        yield 'a group' => [static fn (): PlayQuery => PlayQuery::ofGroup('a b')];
        yield 'a game' => [static fn (): PlayQuery => PlayQuery::ofGroup('club')->forGame('a b')];
    }

    /**
     * @param callable(): PlayQuery $make
     */
    #[DataProvider('queriesOutOfShape')]
    public function testAQueryTakesIdentifiers(callable $make): void
    {
        $this->expectException(InvalidPlay::class);

        $make();
    }

    private function play(string $id, ?DateTimeImmutable $at = null, int $version = 0): Play
    {
        return new Play($id, 'tide', 'ana', [new Seat('ana', confirmed: true)], playedAt: $at, version: $version);
    }

    /**
     * @param iterable<Play> $plays
     *
     * @return list<string>
     */
    private function ids(iterable $plays): array
    {
        $ids = [];

        foreach ($plays as $play) {
            $ids[] = $play->id;
        }

        return $ids;
    }
}
