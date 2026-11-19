<?php

declare(strict_types=1);

namespace Tilttek\LuckTokensBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\LuckTokens\Contract\TokenLedger;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Exception\LedgerConflict;
use Tilttek\LuckTokens\Ledger\EntryType;
use Tilttek\LuckTokens\Ledger\LedgerEntry;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Receipt;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\Store\InMemoryTokenRules;
use Tilttek\LuckTokens\TableRules;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenLedger;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenRules;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Interloper;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Recorder;
use Tilttek\LuckTokensBundle\Tests\Unit\Fixture\Table;

/**
 * What the DBAL stores do on every platform, as the memory ledger of the library does it: the entries kept by table and
 * position, the indexes of the balances, the holders, the operations and the sessions, a writer that meets another
 * refused without writing, the erasure of a member, and the rules kept and read back.
 */
abstract class StoresTestCase extends TestCase
{
    /**
     * The connection of the store under test, through the interloper.
     */
    abstract protected function connection(): Connection;

    /**
     * Another connection to the same database, for the other writer.
     */
    abstract protected function other(): Connection;

    abstract protected function interloper(): Interloper;

    public function testTheLedgerKeepsTheEntriesByTableAndOperationAsTheyWereWritten(): void
    {
        $ledger = $this->ledger();
        $table = Table::ref('table:1');
        $first = $this->entry(1, 'op-1', at: new DateTimeImmutable('2026-11-13 22:15:03.25 America/Toronto'), reason: 'A clever plan — très habile');
        $out = $this->entry(2, 'op-2', type: EntryType::TransferredOut, change: -1, balance: 0);
        $in = $this->entry(3, 'op-2', holder: 'member:Ana', type: EntryType::TransferredIn);

        self::assertSame([], $ledger->entries($table));
        $ledger->append($table, 0, [$first]);
        $ledger->append($table, 1, [$out, $in]);
        $ledger->append($table, 3, []);

        self::assertSame([$first->toArray(), $out->toArray(), $in->toArray()], $this->rows($ledger->entries($table)));
        self::assertSame('2026-11-14T03:15:03.250000Z', $ledger->entries($table)[0]->toArray()['at']);
        self::assertSame([$out->toArray(), $in->toArray()], $this->rows($ledger->operation($table, 'op-2')));
        self::assertSame([], $ledger->operation($table, 'op-3'));
        self::assertSame([], $ledger->entries(Table::ref('table:2')));
        self::assertSame([], $ledger->operation(Table::ref('table:2'), 'op-1'));
        self::assertSame([], $ledger->operation($table, 'OP-1'), 'An operation identifier is compared byte by byte.');
    }

    public function testTheLedgerIndexesTheBalancesTheHoldersAndTheSessions(): void
    {
        $ledger = $this->ledger();
        $table = Table::ref('table:1');
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');

        self::assertSame([0, 0, [], false], [$ledger->last($table), $ledger->balance($table, 'fate', $ana), $ledger->holders($table), $ledger->hasSession($table, 's-1')]);

        $ledger->append($table, 0, [
            $this->entry(1, 'op-1', holder: 'member:bo', change: 2, balance: 2),
            $this->entry(2, 'op-1', change: 3, balance: 3),
            $this->entry(3, 'op-1', holder: 'member:bo', type: EntryType::Spent, change: -1, balance: 1),
            new LedgerEntry($table, 4, 'op-2', EntryType::SessionStarted, null, Table::gm(), 0, 0, Table::gm(), new DateTimeImmutable('2026-11-13 20:00 UTC'), session: 's-1'),
            $this->entry(5, 'op-2', type: EntryType::Refreshed, kind: 'bennies', change: 3, balance: 3, session: 's-1'),
            $this->entry(6, 'op-3', holder: 'member:Ana', change: 1, balance: 1),
        ]);

        self::assertSame(6, $ledger->last($table));
        self::assertSame([1, 3, 3, 0, 1], [$ledger->balance($table, 'fate', $bo), $ledger->balance($table, 'fate', $ana), $ledger->balance($table, 'bennies', $ana), $ledger->balance($table, 'bennies', $bo), $ledger->balance($table, 'fate', Table::ref('member:Ana'))]);
        self::assertEquals([$bo, $ana, Table::ref('member:Ana')], $ledger->holders($table), 'The game master who started the session holds nothing.');
        self::assertTrue($ledger->hasSession($table, 's-1'));
        self::assertFalse($ledger->hasSession($table, 's-2'));
        self::assertFalse($ledger->hasSession(Table::ref('table:2'), 's-1'));
        self::assertSame([0, 0], [$ledger->last(Table::ref('table:2')), $ledger->balance(Table::ref('table:2'), 'fate', $bo)]);
        self::assertSame([], $ledger->holders(Table::ref('table:2')));
    }

    public function testAnAppendAfterAPositionNoLongerLastIsAConflictAndKeepsNothing(): void
    {
        $ledger = $this->ledger();
        $table = Table::ref('table:1');
        $ledger->append($table, 0, [$this->entry(1, 'op-1')]);

        foreach ([0, 2] as $after) {
            try {
                $ledger->append($table, $after, [$this->entry($after + 1, 'op-2')]);
                self::fail('The ledger ends at position 1.');
            } catch (LedgerConflict $ledgerConflict) {
                self::assertSame(\sprintf('The ledger of the table table:1 ends at position 1; entries came after position %d.', $after), $ledgerConflict->getMessage());
            }
        }

        self::assertCount(1, $ledger->entries($table));
        self::assertSame([], $ledger->operation($table, 'op-2'));
    }

    public function testAWriterThatMeetsAnotherAtTheSamePositionWritesNothing(): void
    {
        $ledger = $this->ledger();
        $other = new DbalTokenLedger($this->other());
        $table = Table::ref('table:1');
        $this->interloper()->arm(fn () => $other->append($table, 0, [$this->entry(1, 'op-other')]));

        try {
            $ledger->append($table, 0, [$this->entry(1, 'op-1'), $this->entry(2, 'op-1')]);
            self::fail('The other writer took position 1.');
        } catch (LedgerConflict $ledgerConflict) {
            self::assertSame('The ledger of the table table:1 ends at position 1; entries came after position 0.', $ledgerConflict->getMessage());
        }

        self::assertSame(['op-other'], array_map(static fn (LedgerEntry $entry): string => $entry->operation, $ledger->entries($table)));
        self::assertSame(1, $ledger->last($table));
    }

    public function testEntriesOutOfPlaceAreRefusedAndKeepNothing(): void
    {
        $ledger = $this->ledger();
        $table = Table::ref('table:1');

        foreach ([[$this->entry(1, 'op-1'), $this->entry(3, 'op-1')], [$this->entry(2, 'op-1')], [$this->entry(1, 'op-1', 'table:2')]] as $entries) {
            try {
                $ledger->append($table, 0, $entries);
                self::fail('The entries follow the last one, in the ledger of their table.');
            } catch (InvalidTokenRule $invalid) {
                self::assertSame('The entries of an operation belong to the table of the ledger and follow its last one, by position.', $invalid->getMessage());
            }
        }

        self::assertSame([], $ledger->entries($table));
        self::assertSame([], $ledger->entries(Table::ref('table:2')));
    }

    public function testTheTokensRunOnTheStoreAsOnTheMemoryLedger(): void
    {
        $memory = new InMemoryTokenLedger();
        $dbal = $this->ledger();

        foreach ([$memory, $dbal] as $ledger) {
            $tokens = $this->tokens($ledger);
            $table = Table::ref('table:1');
            $ana = Table::ref('member:ana');
            $bo = Table::ref('member:bo');
            $tokens->grant($table, 'fate', $ana, 2, Table::gm(), 'op-1', 'A clever plan');
            $tokens->spend($table, 'fate', $ana, 1, $ana, 'op-2');
            $tokens->grant($table, 'bennies', $bo, 2, $ana, 'op-3');
            $tokens->transfer($table, 'bennies', $bo, $ana, 1, $bo, 'op-4');
            $tokens->startSession($table, 's-1', [Table::ref('member:cy')], Table::gm(), 'op-5');
            self::assertTrue($tokens->spend($table, 'fate', $ana, 1, $ana, 'op-2')->replayed);
        }

        self::assertSame($this->rows($memory->entries(Table::ref('table:1'))), $this->rows($dbal->entries(Table::ref('table:1'))));
        self::assertEquals($memory->holders(Table::ref('table:1')), $dbal->holders(Table::ref('table:1')));
    }

    public function testAnOperationThatMeetsAnotherWriterReadsAgainAndIsWritten(): void
    {
        $ledger = $this->ledger();
        $tokens = $this->tokens($ledger);
        $others = $this->tokens(new DbalTokenLedger($this->other()));
        $table = Table::ref('table:1');
        $ana = Table::ref('member:ana');
        $tokens->grant($table, 'fate', $ana, 2, Table::gm(), 'op-1');
        $this->interloper()->arm(static fn (): Receipt => $others->grant($table, 'fate', $ana, 5, Table::gm(), 'op-other-2'));
        $this->interloper()->arm(static fn (): Receipt => $others->spend($table, 'fate', $ana, 1, $ana, 'op-other'));

        $receipt = $tokens->spend($table, 'fate', $ana, 2, $ana, 'op-2');
        // Read again after each of the two other writers: the third attempt, the last, is written.

        self::assertSame([4, 4], [$receipt->entries[0]->position, $receipt->balance()]);
        self::assertSame(['op-1', 'op-other-2', 'op-other', 'op-2'], array_map(static fn (LedgerEntry $entry): string => $entry->operation, $ledger->entries($table)));
        self::assertSame(4, $ledger->balance($table, 'fate', $ana));
    }

    public function testAMemberIsErasedUnderAPseudonymInEveryTable(): void
    {
        $ledger = $this->ledger();
        $events = new Recorder();
        $tokens = $this->tokens($ledger, $events);
        $one = Table::ref('table:1');
        $two = Table::ref('table:2');
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');
        $tokens->grant($one, 'fate', $ana, 3, Table::gm(), 'op-1');
        $tokens->spend($one, 'fate', $ana, 1, $ana, 'op-2');
        $tokens->grant($two, 'bennies', $bo, 2, $ana, 'op-1');
        $tokens->grant($two, 'fate', $bo, 1, Table::gm(), 'op-2');
        $tokens->grant($two, 'fate', Table::ref('member:Ana'), 1, Table::gm(), 'op-3');

        $before = $this->rows($ledger->entries($two));
        $seen = \count($events->events);

        $erased = $tokens->eraseHolder($ana);

        self::assertNotNull($erased);
        self::assertSame(3, $erased->entries);
        self::assertSame([$erased], \array_slice($events->events, $seen));
        self::assertSame([2, 0], [$ledger->balance($one, 'fate', $erased->pseudonym), $ledger->balance($one, 'fate', $ana)]);
        self::assertEquals([$erased->pseudonym], $ledger->holders($one));
        self::assertSame([$erased->pseudonym->toString(), $erased->pseudonym->toString()], [$ledger->entries($one)[1]->holder->toString(), $ledger->entries($one)[1]->actor->toString()]);
        self::assertSame([[1, 3, 3], [2, -1, 2]], array_map(static fn (LedgerEntry $entry): array => [$entry->position, $entry->change, $entry->balance], $ledger->entries($one)));
        $after = $this->rows($ledger->entries($two));
        self::assertSame($erased->pseudonym->toString(), $after[0]['actor']);
        self::assertSame(array_replace($before[0], ['actor' => $erased->pseudonym->toString()]), $after[0]);
        self::assertSame(\array_slice($before, 1), \array_slice($after, 1), 'Another member, of a reference that differs only by case, is left as they were.');
        self::assertNull($tokens->eraseHolder($ana));
        self::assertCount($seen + 1, $events->events);
    }

    public function testTheStoreRefusesThePseudonymOfTheMemberThemselves(): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains('A member is erased under a pseudonym of their own, not their reference.');

        $this->ledger()->eraseHolder(Table::ref('member:ana'), Table::ref('member:ana'));
    }

    public function testTheRulesAreKeptByTableAndReadBack(): void
    {
        $store = new DbalTokenRules($this->connection());
        $rules = Table::rules();

        self::assertNull($store->rulesFor(Table::ref('table:1')));
        $store->save($rules);
        self::assertEquals($rules, $store->rulesFor(Table::ref('table:1')));
        self::assertNull($store->rulesFor(Table::ref('table:2')));

        $store->save(Table::rules('table:2'));

        $other = TableRules::fromArray(Table::ref('table:1'), ['kinds' => [['key' => 'luck', 'name' => 'Chance — à la française', 'cap' => 3]]]);
        $store->save($other);
        self::assertEquals($other, $store->rulesFor(Table::ref('table:1')));
        self::assertEquals(Table::rules('table:2'), $store->rulesFor(Table::ref('table:2')), 'Saving the rules of a table leaves those of the others.');

        self::assertTrue($store->remove(Table::ref('table:1')));
        self::assertFalse($store->remove(Table::ref('table:1')));
        self::assertNull($store->rulesFor(Table::ref('table:1')));
        self::assertEquals(Table::rules('table:2'), $store->rulesFor(Table::ref('table:2')), 'Forgetting the rules of a table leaves those of the others.');
    }

    protected function ledger(): DbalTokenLedger
    {
        return new DbalTokenLedger($this->connection());
    }

    private function tokens(TokenLedger $ledger, ?Recorder $events = null): LuckTokens
    {
        $rules = new InMemoryTokenRules();
        $rules->save(Table::rules('table:1'));
        $rules->save(Table::rules('table:2'));

        return new LuckTokens($rules, $ledger, $events, clock: new MockClock('2026-11-13 19:30:00.250000', 'America/Toronto'));
    }

    /**
     * @param list<LedgerEntry> $entries
     *
     * @return list<array<string, mixed>>
     */
    private function rows(array $entries): array
    {
        return array_map(static fn (LedgerEntry $entry): array => $entry->toArray(), $entries);
    }

    private function entry(int $position, string $operation, string $table = 'table:1', string $holder = 'member:ana', EntryType $type = EntryType::Granted, string $kind = 'fate', int $change = 1, int $balance = 1, ?DateTimeImmutable $at = null, ?string $reason = null, ?string $session = null): LedgerEntry
    {
        return new LedgerEntry(ResourceReference::fromString($table), $position, $operation, $type, $kind, ResourceReference::fromString($holder), $change, $balance, Table::gm(), $at ?? new DateTimeImmutable('2026-11-13 20:00:00.5 UTC'), $reason, $session);
    }
}
