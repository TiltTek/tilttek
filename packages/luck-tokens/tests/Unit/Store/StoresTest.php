<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Tests\Unit\Store;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Exception\LedgerConflict;
use Tilttek\LuckTokens\Ledger\EntryType;
use Tilttek\LuckTokens\Ledger\LedgerEntry;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\Store\InMemoryTokenRules;
use Tilttek\LuckTokens\Store\SelfAccess;
use Tilttek\LuckTokens\TableRules;
use Tilttek\LuckTokens\Tests\Unit\Fixture\Table;

#[CoversClass(InMemoryTokenLedger::class)]
#[CoversClass(InMemoryTokenRules::class)]
#[CoversClass(SelfAccess::class)]
#[CoversClass(LedgerConflict::class)]
final class StoresTest extends TestCase
{
    public function testTheLedgerKeepsTheEntriesByTableAndOperation(): void
    {
        $ledger = new InMemoryTokenLedger();
        $table = Table::ref('table:1');
        $first = $this->entry(1, 'op-1');
        $out = $this->entry(2, 'op-2');
        $in = $this->entry(3, 'op-2');

        self::assertSame([], $ledger->entries($table));
        $ledger->append($table, 0, [$first]);
        $ledger->append($table, 1, [$out, $in]);
        $ledger->append($table, 3, []);

        self::assertSame([$first, $out, $in], $ledger->entries($table));
        self::assertSame([$out, $in], $ledger->operation($table, 'op-2'));
        self::assertSame([], $ledger->operation($table, 'op-3'));
        self::assertSame([], $ledger->entries(Table::ref('table:2')));
        self::assertSame([], $ledger->operation(Table::ref('table:2'), 'op-1'));
    }

    public function testTheLedgerIndexesTheBalancesTheHoldersAndTheSessions(): void
    {
        $ledger = new InMemoryTokenLedger();
        $table = Table::ref('table:1');
        $at = new DateTimeImmutable('2026-11-13 20:00 UTC');
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');

        self::assertSame([0, 0, [], false], [$ledger->last($table), $ledger->balance($table, 'fate', $ana), $ledger->holders($table), $ledger->hasSession($table, 's-1')]);

        $ledger->append($table, 0, [
            new LedgerEntry($table, 1, 'op-1', EntryType::Granted, 'fate', $bo, 2, 2, Table::gm(), $at),
            new LedgerEntry($table, 2, 'op-1', EntryType::Granted, 'fate', $ana, 3, 3, Table::gm(), $at),
            new LedgerEntry($table, 3, 'op-1', EntryType::Spent, 'fate', $bo, -1, 1, Table::gm(), $at),
            new LedgerEntry($table, 4, 'op-2', EntryType::SessionStarted, null, Table::gm(), 0, 0, Table::gm(), $at, session: 's-1'),
            new LedgerEntry($table, 5, 'op-2', EntryType::Refreshed, 'bennies', $ana, 3, 3, Table::gm(), $at, session: 's-1'),
        ]);

        self::assertSame(5, $ledger->last($table));
        self::assertSame([1, 3, 3, 0], [$ledger->balance($table, 'fate', $bo), $ledger->balance($table, 'fate', $ana), $ledger->balance($table, 'bennies', $ana), $ledger->balance($table, 'bennies', $bo)]);
        self::assertSame([$bo, $ana], $ledger->holders($table), 'The game master who started the session holds nothing.');
        self::assertTrue($ledger->hasSession($table, 's-1'));
        self::assertFalse($ledger->hasSession($table, 's-2'));
        self::assertFalse($ledger->hasSession(Table::ref('table:2'), 's-1'));
        self::assertSame(0, $ledger->balance(Table::ref('table:2'), 'fate', $bo));
        self::assertSame([], $ledger->holders(Table::ref('table:2')));
    }

    public function testAnAppendAfterAPositionNoLongerLastIsAConflictAndKeepsNothing(): void
    {
        $ledger = new InMemoryTokenLedger();
        $table = Table::ref('table:1');
        $ledger->append($table, 0, [$this->entry(1, 'op-1')]);

        try {
            $ledger->append($table, 0, [$this->entry(1, 'op-2')]);
            self::fail('The ledger ends at position 1.');
        } catch (LedgerConflict $ledgerConflict) {
            self::assertSame('The ledger of the table table:1 ends at position 1; entries came after position 0.', $ledgerConflict->getMessage());
        }

        self::assertCount(1, $ledger->entries($table));
        self::assertSame([], $ledger->operation($table, 'op-2'));
    }

    public function testEntriesOutOfPlaceAreRefusedAndKeepNothing(): void
    {
        $ledger = new InMemoryTokenLedger();
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
        self::assertSame([], $ledger->operation($table, 'op-1'));
    }

    public function testTheRulesAreKeptByTable(): void
    {
        $store = new InMemoryTokenRules();
        $rules = TableRules::fromJson(Table::ref('table:1'), Table::RULES);
        $store->save($rules);

        self::assertSame($rules, $store->rulesFor(Table::ref('table:1')));
        self::assertNull($store->rulesFor(Table::ref('table:2')));

        $other = TableRules::fromArray(Table::ref('table:1'), []);
        $store->save($other);
        self::assertSame($other, $store->rulesFor(Table::ref('table:1')));
    }

    public function testEachPlayerControlsTheirOwnTokens(): void
    {
        self::assertTrue(new SelfAccess()->controls(Table::ref('member:ana'), Table::ref('member:ana')));
        self::assertFalse(new SelfAccess()->controls(Table::ref('member:ana'), Table::ref('member:bo')));
    }

    private function entry(int $position, string $operation, string $table = 'table:1'): LedgerEntry
    {
        return new LedgerEntry(Table::ref($table), $position, $operation, EntryType::Granted, 'fate', Table::ref('member:ana'), 1, $position, Table::gm(), new DateTimeImmutable('2026-11-13 20:00 UTC'));
    }
}
