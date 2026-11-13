<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Tests\Unit\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\SecretRolls\Exception\JournalConflict;
use Tilttek\SecretRolls\Journal\JournalEntry;
use Tilttek\SecretRolls\Store\InMemorySecretRollStore;
use Tilttek\SecretRolls\Store\NoPublisher;
use Tilttek\SecretRolls\Tests\Unit\Fixture\Table;

#[CoversClass(InMemorySecretRollStore::class)]
#[CoversClass(NoPublisher::class)]
#[CoversClass(JournalConflict::class)]
final class InMemorySecretRollStoreTest extends TestCase
{
    public function testItKeepsTheEntriesByScenePositionAndTheSealedRolls(): void
    {
        $store = new InMemorySecretRollStore();
        $hidden = new Table()->rolls->roll('cave', '1d6');
        $entry = JournalEntry::committed($hidden, JournalEntry::GENESIS);

        self::assertSame([], $store->entries('cave'));
        self::assertNull($store->hidden($hidden->roll()));

        $store->commit($entry, $hidden);
        $closing = JournalEntry::closed('cave', 2, Table::at(), $entry->hash);
        $store->append($closing);

        self::assertSame([$entry, $closing], $store->entries('cave'));
        self::assertSame([], $store->entries('forest'));
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
        self::assertNull($store->hidden($hidden->roll()));
    }

    public function testNoPublisherPushesNothing(): void
    {
        $entry = JournalEntry::closed('cave', 1, Table::at(), JournalEntry::GENESIS);
        new NoPublisher()->publish($entry);

        self::assertTrue($entry->holds());
    }
}
