<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\LuckTokens\Event\HolderErased;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Exception\TokenRefused;
use Tilttek\LuckTokens\Ledger\EntryType;
use Tilttek\LuckTokens\Ledger\LedgerEntry;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\Store\InMemoryTokenRules;
use Tilttek\LuckTokens\TableRules;
use Tilttek\LuckTokens\Tests\Unit\Fixture\Table;

/**
 * A member erased from the ledgers: a pseudonym of its own in their place, as holder and as actor, the balances, the
 * positions and the order left as they were, once.
 */
#[CoversClass(LuckTokens::class)]
#[CoversClass(HolderErased::class)]
#[CoversClass(InMemoryTokenLedger::class)]
#[CoversClass(LedgerEntry::class)]
final class ErasureTest extends TestCase
{
    public function testAPseudonymTakesThePlaceOfTheMemberAndTheBalancesStay(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');
        $t->tokens->grant($t->table, 'fate', $bo, 1, Table::gm(), 'op-1');
        $t->tokens->grant($t->table, 'fate', $ana, 3, Table::gm(), 'op-2', 'A clever plan');
        $t->tokens->spend($t->table, 'fate', $ana, 1, $ana, 'op-3');
        $t->tokens->grant($t->table, 'bennies', $bo, 2, $ana, 'op-4');
        $t->tokens->transfer($t->table, 'bennies', $bo, $ana, 1, $bo, 'op-5');
        $t->tokens->startSession($t->table, 's-1', [], Table::gm(), 'op-6');

        $before = $t->tokens->log($t->table);

        $erased = $t->tokens->eraseHolder($ana);

        self::assertInstanceOf(HolderErased::class, $erased);
        self::assertSame(LuckTokens::ERASED_KIND, $erased->pseudonym->key);
        self::assertSame('erased', $erased->pseudonym->key);
        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/D', $erased->pseudonym->id);
        $pseudonym = $erased->pseudonym->toString();
        $after = $t->tokens->log($t->table);
        self::assertSame(
            [
                [1, 'granted', 'fate', 'member:bo', 1, 1, 'member:gm', null],
                [2, 'granted', 'fate', $pseudonym, 3, 3, 'member:gm', 'A clever plan'],
                [3, 'spent', 'fate', $pseudonym, -1, 2, $pseudonym, null],
                [4, 'granted', 'bennies', 'member:bo', 2, 2, $pseudonym, null],
                [5, 'transferred-out', 'bennies', 'member:bo', -1, 1, 'member:bo', null],
                [6, 'transferred-in', 'bennies', $pseudonym, 1, 1, 'member:bo', null],
                [7, 'session-started', null, 'member:gm', 0, 0, 'member:gm', null],
                [8, 'refreshed', 'bennies', 'member:bo', 2, 3, 'member:gm', null],
                [9, 'refreshed', 'bennies', $pseudonym, 2, 3, 'member:gm', null],
                [10, 'refreshed', 'fate', 'member:bo', 2, 3, 'member:gm', null],
                [11, 'refreshed', 'fate', $pseudonym, 1, 3, 'member:gm', null],
            ],
            array_map(static fn (LedgerEntry $entry): array => [$entry->position, $entry->type->value, $entry->kind, $entry->holder->toString(), $entry->change, $entry->balance, $entry->actor->toString(), $entry->reason], $after),
        );
        self::assertSame(6, $erased->entries, 'Each entry is counted once, even when the member is its holder and its actor.');
        self::assertSame(array_map(static fn (LedgerEntry $entry): string => $entry->operation, $before), array_map(static fn (LedgerEntry $entry): string => $entry->operation, $after));
        self::assertSame(array_map(static fn (LedgerEntry $entry): string => $entry->toArray()['at'], $before), array_map(static fn (LedgerEntry $entry): string => $entry->toArray()['at'], $after));

        self::assertSame(3, $t->tokens->balance($t->table, 'fate', $erased->pseudonym));
        self::assertSame(3, $t->tokens->balance($t->table, 'bennies', $erased->pseudonym));
        self::assertSame(0, $t->tokens->balance($t->table, 'fate', $ana));
        self::assertSame(['member:bo', $pseudonym], array_map(static fn (ResourceReference $holder): string => $holder->toString(), $t->ledger->holders($t->table)));
        self::assertSame([], $t->tokens->log($t->table, $ana));
        self::assertCount(5, $t->tokens->log($t->table, $erased->pseudonym));
        self::assertSame([$after[2]], $t->ledger->operation($t->table, 'op-3'));
        self::assertSame(\array_slice($after, 6), $t->ledger->operation($t->table, 'op-6'));
        self::assertTrue($t->ledger->hasSession($t->table, 's-1'));
        self::assertSame(11, $t->ledger->last($t->table));
        self::assertSame($erased, array_last($t->events->events));
    }

    public function testAnErasureSentAgainWritesNothingAndTellsNothing(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $t->tokens->grant($t->table, 'fate', $ana, 2, Table::gm(), 'op-1');
        $first = $t->tokens->eraseHolder($ana);
        $log = $t->tokens->log($t->table);
        $events = \count($t->events->events);

        self::assertNotNull($first);
        self::assertNull($t->tokens->eraseHolder($ana));
        self::assertNull($t->tokens->eraseHolder(Table::ref('member:nobody')));
        self::assertSame($log, $t->tokens->log($t->table));
        self::assertCount($events, $t->events->events);
    }

    public function testEachErasureDrawsItsOwnPseudonym(): void
    {
        $t = new Table();
        $t->tokens->grant($t->table, 'fate', Table::ref('member:ana'), 1, Table::gm(), 'op-1');
        $t->tokens->grant($t->table, 'fate', Table::ref('member:bo'), 1, Table::gm(), 'op-2');

        $ana = $t->tokens->eraseHolder(Table::ref('member:ana'));
        $bo = $t->tokens->eraseHolder(Table::ref('member:bo'));

        self::assertNotNull($ana);
        self::assertNotNull($bo);
        self::assertFalse($ana->pseudonym->equals($bo->pseudonym));
        self::assertSame([1, 1], [$t->tokens->balance($t->table, 'fate', $ana->pseudonym), $t->tokens->balance($t->table, 'fate', $bo->pseudonym)]);
    }

    public function testWithoutADispatcherTheMemberIsErasedAllTheSame(): void
    {
        $rules = new InMemoryTokenRules();
        $rules->save(TableRules::fromJson(Table::ref('table:1'), Table::RULES));

        $ledger = new InMemoryTokenLedger();
        $tokens = new LuckTokens($rules, $ledger);
        $tokens->grant(Table::ref('table:1'), 'fate', Table::ref('member:ana'), 2, Table::gm(), 'op-1');

        $erased = $tokens->eraseHolder(Table::ref('member:ana'));

        self::assertNotNull($erased);
        self::assertSame(1, $erased->entries);
        self::assertSame(2, $ledger->balance(Table::ref('table:1'), 'fate', $erased->pseudonym));
    }

    public function testAnOperationOfTheErasedMemberSentAgainNamesAnotherOperation(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $t->tokens->grant($t->table, 'fate', $ana, 2, Table::gm(), 'op-1');
        $t->tokens->eraseHolder($ana);

        $this->expectException(TokenRefused::class);
        $this->expectExceptionMessageIsOrContains('The operation op-1 was done already, with other values');
        $t->tokens->grant($t->table, 'fate', $ana, 2, Table::gm(), 'op-1');
    }

    public function testTheStoreErasesTheMemberInEveryTableAndLeavesTheOthersAsTheyWere(): void
    {
        $ledger = new InMemoryTokenLedger();
        $at = new DateTimeImmutable('2026-11-13 20:00 UTC');
        $one = Table::ref('table:1');
        $two = Table::ref('table:2');
        $three = Table::ref('table:3');
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');
        $pseudonym = Table::ref('erased:0123456789abcdef0123456789abcdef');
        $ledger->append($one, 0, [new LedgerEntry($one, 1, 'op-1', EntryType::Granted, 'fate', $ana, 2, 2, Table::gm(), $at)]);
        $ledger->append($two, 0, [
            new LedgerEntry($two, 1, 'op-1', EntryType::Granted, 'fate', $bo, 1, 1, $ana, $at),
            new LedgerEntry($two, 2, 'op-2', EntryType::Granted, 'fate', $ana, 1, 1, Table::gm(), $at),
        ]);
        $untouched = new LedgerEntry($three, 1, 'op-1', EntryType::Granted, 'fate', $bo, 1, 1, Table::gm(), $at);
        $ledger->append($three, 0, [$untouched]);

        self::assertSame(3, $ledger->eraseHolder($ana, $pseudonym));
        self::assertSame([2, 0], [$ledger->balance($one, 'fate', $pseudonym), $ledger->balance($one, 'fate', $ana)]);
        self::assertSame([1, 1], [$ledger->balance($two, 'fate', $pseudonym), $ledger->balance($two, 'fate', $bo)]);
        self::assertSame([$bo, $pseudonym], $ledger->holders($two));
        self::assertTrue($ledger->entries($two)[0]->actor->equals($pseudonym));
        self::assertSame([$untouched], $ledger->entries($three));
        self::assertSame([$untouched], $ledger->operation($three, 'op-1'));
        self::assertCount(1, $ledger->operation($two, 'op-1'));
        self::assertSame(0, $ledger->eraseHolder($ana, Table::ref('erased:ffffffffffffffffffffffffffffffff')));
    }

    public function testTheStoreRefusesThePseudonymOfTheMemberThemselves(): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains('A member is erased under a pseudonym of their own, not their reference.');

        new InMemoryTokenLedger()->eraseHolder(Table::ref('member:ana'), Table::ref('member:ana'));
    }

    public function testAnEntryNamesThePseudonymWhereItNamedTheMember(): void
    {
        $at = new DateTimeImmutable('2026-11-13 20:00:00.5 UTC');
        $table = Table::ref('table:1');
        $ana = Table::ref('member:ana');
        $pseudonym = Table::ref('erased:1');
        $held = new LedgerEntry($table, 4, 'op-1', EntryType::Spent, 'fate', $ana, -1, 2, Table::gm(), $at, 'Reroll', 's-1');
        $done = new LedgerEntry($table, 5, 'op-2', EntryType::Granted, 'fate', Table::ref('member:bo'), 1, 1, $ana, $at);
        $both = new LedgerEntry($table, 6, 'op-3', EntryType::Spent, 'fate', $ana, -1, 1, $ana, $at);
        $other = new LedgerEntry($table, 7, 'op-4', EntryType::Granted, 'fate', Table::ref('member:bo'), 1, 2, Table::gm(), $at);

        self::assertSame(array_replace($held->toArray(), ['holder' => 'erased:1']), $held->pseudonymized($ana, $pseudonym)->toArray());
        self::assertSame(array_replace($done->toArray(), ['actor' => 'erased:1']), $done->pseudonymized($ana, $pseudonym)->toArray());
        self::assertSame(array_replace($both->toArray(), ['holder' => 'erased:1', 'actor' => 'erased:1']), $both->pseudonymized($ana, $pseudonym)->toArray());
        self::assertSame($other, $other->pseudonymized($ana, $pseudonym));
    }
}
