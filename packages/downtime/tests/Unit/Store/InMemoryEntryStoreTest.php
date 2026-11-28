<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Downtime\Entry;
use Tilttek\Downtime\Exception\DowntimeConflict;
use Tilttek\Downtime\Exception\InvalidDowntime;
use Tilttek\Downtime\Store\InMemoryEntryStore;
use Tilttek\Downtime\Tests\Unit\Fixture\Rules;

#[CoversClass(InMemoryEntryStore::class)]
#[CoversClass(DowntimeConflict::class)]
final class InMemoryEntryStoreTest extends TestCase
{
    private function entry(string $id, string $character = 'aria', string $campaign = 'campaign-1'): Entry
    {
        return new Entry($id, $campaign, $character, 'member-1', Rules::SYSTEM, Rules::training());
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<string>
     */
    private function ids(array $entries): array
    {
        return array_map(static fn (Entry $entry): string => $entry->id, $entries);
    }

    public function testAnEntryNeverKeptIsNull(): void
    {
        self::assertNull(new InMemoryEntryStore()->entry('a1'));
    }

    public function testEntriesAreKeptAtTheNextVersion(): void
    {
        $store = new InMemoryEntryStore();
        $kept = $store->save([$this->entry('a1'), $this->entry('a2')]);

        self::assertSame(['a1', 'a2'], $this->ids($kept));
        self::assertSame([1, 1], array_map(static fn (Entry $entry): int => $entry->version, $kept));
        self::assertSame($kept[0], $store->entry('a1'));
        self::assertSame(2, $store->save([$kept[0]->advancedBy(1)])[0]->version);
        self::assertSame([], $store->save([]));
    }

    public function testAStaleEntryIsRefused(): void
    {
        $store = new InMemoryEntryStore();
        [$kept] = $store->save([$this->entry('a1')]);
        $store->save([$kept->advancedBy(1)]);

        $this->expectException(DowntimeConflict::class);
        $this->expectExceptionMessageIsOrContains('The entry a1 changed since it was read.');

        $store->save([$kept->advancedBy(2)]);
    }

    public function testANewEntryOfAKeptIdentifierIsRefused(): void
    {
        $store = new InMemoryEntryStore();
        $store->save([$this->entry('a1')]);

        $this->expectException(DowntimeConflict::class);

        $store->save([$this->entry('a1', 'bram')]);
    }

    public function testAnEntryNeverKeptIsNotKeptAtAVersion(): void
    {
        $this->expectException(DowntimeConflict::class);

        new InMemoryEntryStore()->save([$this->entry('a1')->withVersion(1)]);
    }

    public function testEntriesAreKeptAllOrNone(): void
    {
        $store = new InMemoryEntryStore();
        [$kept] = $store->save([$this->entry('a1')]);

        try {
            $store->save([$this->entry('a2'), $kept->withVersion(5)]);
            self::fail('A stale entry was kept.');
        } catch (DowntimeConflict) {
        }

        self::assertNull($store->entry('a2'));
        self::assertSame($kept, $store->entry('a1'));
    }

    public function testOneEntryIsKeptTwiceInOneCallAtItsVersions(): void
    {
        $store = new InMemoryEntryStore();
        $entry = $this->entry('a1');
        $kept = $store->save([$entry, $entry->withVersion(1)->advancedBy(1)]);

        self::assertSame([1, 2], array_map(static fn (Entry $entry): int => $entry->version, $kept));
        self::assertSame(1, $store->entry('a1')?->progress);

        $this->expectException(DowntimeConflict::class);

        $store->save([$kept[1], $kept[1]]);
    }

    public function testTheEntriesAreReadInTheOrderChosen(): void
    {
        $store = new InMemoryEntryStore();
        $store->save([$this->entry('z1'), $this->entry('b1', 'bram'), $this->entry('a1'), $this->entry('o1', 'aria', 'campaign-2')]);
        [$z1] = $store->save([$store->entry('z1')?->advancedBy(3) ?? $this->entry('none')]);
        $store->save([$z1->confirmed('learned')]);

        self::assertSame(['z1', 'a1'], $this->ids($store->entriesOf('campaign-1', 'aria', 10)));
        self::assertSame(['z1'], $this->ids($store->entriesOf('campaign-1', 'aria', 1)));
        self::assertSame(['a1'], $this->ids($store->openOf('campaign-1', 'aria', 10)));
        self::assertSame(['b1', 'a1'], $this->ids($store->queuedIn('campaign-1', 10)));
        self::assertSame(['b1'], $this->ids($store->queuedIn('campaign-1', 1)));
        self::assertSame(['o1'], $this->ids($store->queuedIn('campaign-2', 10)));
        self::assertSame([], $store->entriesOf('campaign-2', 'bram', 10));
    }

    /**
     * @return iterable<string, array{callable(InMemoryEntryStore): mixed}>
     */
    public static function reads(): iterable
    {
        yield 'a log' => [static fn (InMemoryEntryStore $store): array => $store->entriesOf('campaign-1', 'aria', 0)];
        yield 'the open entries' => [static fn (InMemoryEntryStore $store): array => $store->openOf('campaign-1', 'aria', 0)];
        yield 'the queued entries' => [static fn (InMemoryEntryStore $store): array => $store->queuedIn('campaign-1', 0)];
    }

    /**
     * @param callable(InMemoryEntryStore): mixed $read
     */
    #[DataProvider('reads')]
    public function testAReadTakesAtLeastOneEntry(callable $read): void
    {
        $this->expectException(InvalidDowntime::class);
        $this->expectExceptionMessageIsOrContains('A store reads at least one entry at once.');

        $read(new InMemoryEntryStore());
    }
}
