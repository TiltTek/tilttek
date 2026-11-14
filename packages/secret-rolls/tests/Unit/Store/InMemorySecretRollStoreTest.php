<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\SecretRolls\Exception\JournalConflict;
use Tilttek\SecretRolls\Journal\JournalEntry;
use Tilttek\SecretRolls\Journal\JournalHead;
use Tilttek\SecretRolls\Store\InMemorySecretRollStore;
use Tilttek\SecretRolls\Store\NoPublisher;
use Tilttek\SecretRolls\Tests\Unit\Fixture\Table;

#[CoversClass(InMemorySecretRollStore::class)]
#[CoversClass(NoPublisher::class)]
#[CoversClass(JournalConflict::class)]
#[CoversClass(JournalHead::class)]
final class InMemorySecretRollStoreTest extends TestCase
{
    public function testItKeepsTheEntriesByScenePositionAndTheSealedRolls(): void
    {
        $store = new InMemorySecretRollStore();
        $hidden = new Table()->rolls->roll('cave', '1d6');
        $entry = JournalEntry::committed($hidden, JournalEntry::GENESIS);

        self::assertSame([], $store->entries('cave'));
        self::assertNull($store->hidden($hidden->roll()));
        self::assertSame(['cave', 0, JournalEntry::GENESIS, false, 1], $this->head($store->head('cave')));

        $store->commit($entry, $hidden);
        self::assertSame(['cave', 1, $entry->hash, false, 2], $this->head($store->head('cave')));
        $closing = JournalEntry::closed('cave', 2, Table::at(), $entry->hash);
        $store->append($closing);
        self::assertSame(['cave', 2, $closing->hash, true, 3], $this->head($store->head('cave')));

        // A reveal after the closing keeps the scene closed.
        $reveal = JournalEntry::revealed($hidden->opening, 3, Table::at(), $closing->hash);
        $store->append($reveal);
        self::assertSame(['cave', 3, $reveal->hash, true, 4], $this->head($store->head('cave')));

        self::assertSame([$entry, $closing, $reveal], $store->entries('cave'));
        self::assertSame([], $store->entries('forest'));
        self::assertSame(['forest', 0, JournalEntry::GENESIS, false, 1], $this->head($store->head('forest')));
        self::assertSame($hidden, $store->hidden($hidden->roll()));
    }

    public function testAnEntryOutOfPlaceIsAConflictAndKeepsNothing(): void
    {
        $store = new InMemorySecretRollStore();
        $hidden = new Table()->rolls->roll('cave', '1d6');

        try {
            $store->commit(JournalEntry::closed('cave', 2, Table::at(), JournalEntry::GENESIS), $hidden);
            self::fail('An entry out of place was appended.');
        } catch (JournalConflict $journalConflict) {
            self::assertSame('The journal of the scene cave expects position 1; an entry came at position 2.', $journalConflict->getMessage());
        }

        self::assertSame([], $store->entries('cave'));
        self::assertSame(['cave', 0, JournalEntry::GENESIS, false, 1], $this->head($store->head('cave')));
        self::assertNull($store->hidden($hidden->roll()));
    }

    public function testNoPublisherPushesNothing(): void
    {
        $entry = JournalEntry::closed('cave', 1, Table::at(), JournalEntry::GENESIS);
        new NoPublisher()->publish($entry);

        self::assertTrue($entry->holds());
    }

    /**
     * @return array{string, int, string, bool, int}
     */
    private function head(JournalHead $head): array
    {
        return [$head->scene, $head->length, $head->hash, $head->closed, $head->nextPosition()];
    }
}
