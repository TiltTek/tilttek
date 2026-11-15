<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Tests\Unit;

use Closure;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\LuckTokens\Balance;
use Tilttek\LuckTokens\Contract\HolderAccess;
use Tilttek\LuckTokens\Event\SessionStarted;
use Tilttek\LuckTokens\Event\TokensGranted;
use Tilttek\LuckTokens\Event\TokensSpent;
use Tilttek\LuckTokens\Event\TokensTransferred;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Exception\LedgerConflict;
use Tilttek\LuckTokens\Exception\TokenRefused;
use Tilttek\LuckTokens\Ledger\EntryType;
use Tilttek\LuckTokens\Ledger\LedgerEntry;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Receipt;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\Store\InMemoryTokenRules;
use Tilttek\LuckTokens\Tests\Unit\Fixture\BusyLedger;
use Tilttek\LuckTokens\Tests\Unit\Fixture\Table;

#[CoversClass(LuckTokens::class)]
#[CoversClass(Receipt::class)]
#[CoversClass(Balance::class)]
#[CoversClass(TokensGranted::class)]
#[CoversClass(TokensSpent::class)]
#[CoversClass(TokensTransferred::class)]
#[CoversClass(SessionStarted::class)]
#[CoversClass(TokenRefused::class)]
final class LuckTokensTest extends TestCase
{
    public function testAGameMasterGrantsAndAPlayerSpends(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');

        $granted = $t->tokens->grant($t->table, 'fate', $ana, 2, Table::gm(), 'op-1', 'A clever plan');

        self::assertFalse($granted->replayed);
        self::assertSame(2, $granted->balance());
        self::assertSame('op-1', $granted->operation);
        self::assertSame([
            'table' => 'table:1',
            'position' => 1,
            'operation' => 'op-1',
            'type' => 'granted',
            'kind' => 'fate',
            'holder' => 'member:ana',
            'change' => 2,
            'balance' => 2,
            'actor' => 'member:gm',
            'at' => '2026-11-14T00:30:00.250000Z',
            'reason' => 'A clever plan',
            'session' => null,
        ], $granted->entries[0]->toArray());

        $spent = $t->tokens->spend($t->table, 'fate', $ana, 1, $ana, 'op-2');
        self::assertSame([2, -1, 1, null], [$spent->entries[0]->position, $spent->entries[0]->change, $spent->balance(), $spent->entries[0]->reason]);
        self::assertSame(1, $t->tokens->balance($t->table, 'fate', $ana));
        self::assertSame(0, $t->tokens->balance($t->table, 'inspiration', $ana));

        self::assertCount(2, $t->events->events);
        [$grantedEvent, $spentEvent] = $t->events->events;
        self::assertInstanceOf(TokensGranted::class, $grantedEvent);
        self::assertSame([$granted->entries[0], 2], [$grantedEvent->entry, $grantedEvent->amount()]);
        self::assertInstanceOf(TokensSpent::class, $spentEvent);
        self::assertSame([$spent->entries[0], 1], [$spentEvent->entry, $spentEvent->amount()]);
    }

    public function testAnOperationSentAgainWritesNothingMore(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $first = $t->tokens->grant($t->table, 'fate', $ana, 2, Table::gm(), 'op-1');
        $again = $t->tokens->grant($t->table, 'fate', $ana, 2, Table::gm(), 'op-1', 'Another reason is the same operation');

        self::assertTrue($again->replayed);
        self::assertSame($first->entries, $again->entries);
        self::assertSame(2, $t->tokens->balance($t->table, 'fate', $ana));
        self::assertCount(1, $t->events->events);

        $spent = $t->tokens->spend($t->table, 'fate', $ana, 2, $ana, 'op-2');
        self::assertTrue($t->tokens->spend($t->table, 'fate', $ana, 2, $ana, 'op-2')->replayed, 'A spending sent again is not refused for want of tokens.');
        self::assertSame([...$first->entries, ...$spent->entries], $t->tokens->log($t->table, $ana));
    }

    public function testAnIdentifierNamesOneOperation(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $t->tokens->grant($t->table, 'fate', $ana, 2, Table::gm(), 'op-1');

        foreach ([
            static fn (): Receipt => $t->tokens->grant($t->table, 'fate', $ana, 3, Table::gm(), 'op-1'),
            static fn (): Receipt => $t->tokens->grant($t->table, 'bennies', $ana, 2, Table::gm(), 'op-1'),
            static fn (): Receipt => $t->tokens->grant($t->table, 'fate', Table::ref('member:bo'), 2, Table::gm(), 'op-1'),
            static fn (): Receipt => $t->tokens->spend($t->table, 'fate', $ana, 2, Table::gm(), 'op-1'),
            static fn (): Receipt => $t->tokens->grant($t->table, 'bennies', $ana, 2, $ana, 'op-1'),
        ] as $reuse) {
            try {
                $reuse();
                self::fail('The identifier names another operation.');
            } catch (TokenRefused $refused) {
                self::assertSame('The operation op-1 was done already, with other values; an operation identifier names one operation.', $refused->getMessage());
            }
        }

        self::assertCount(1, $t->tokens->log($t->table));
    }

    public function testGrantsAreForTheGameMastersUnlessTheKindSaysOtherwise(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');

        $this->assertRefused('Only a game master of the table grants "fate"; member:ana is not one.', static fn (): Receipt => $t->tokens->grant($t->table, 'fate', $bo, 1, $ana, 'op-1'));
        self::assertSame(1, $t->tokens->grant($t->table, 'bennies', $bo, 1, $ana, 'op-2')->balance());
        self::assertSame([], $t->tokens->log($t->table, $ana));
    }

    public function testABalanceStaysWithinItsCap(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $t->tokens->grant($t->table, 'bennies', $ana, 4, Table::gm(), 'op-1');

        self::assertSame(5, $t->tokens->grant($t->table, 'bennies', $ana, 1, Table::gm(), 'op-2')->balance());
        $this->assertRefused('member:ana holds 5 "bennies" of 5 at most; 1 more would go over.', static fn (): Receipt => $t->tokens->grant($t->table, 'bennies', $ana, 1, Table::gm(), 'op-3'));
        $this->assertInvalid('An amount of "bennies" is 1 to 5, its cap; not 6.', static fn (): Receipt => $t->tokens->grant($t->table, 'bennies', $ana, 6, Table::gm(), 'op-4'));
        $this->assertInvalid('An amount of "bennies" is 1 to 5, its cap; not 0.', static fn (): Receipt => $t->tokens->spend($t->table, 'bennies', $ana, 0, $ana, 'op-5'));
        $this->assertInvalid('An amount of "inspiration" is 1 to 1, its cap; not -1.', static fn (): Receipt => $t->tokens->transfer($t->table, 'inspiration', $ana, Table::gm(), -1, $ana, 'op-6'));
    }

    public function testABalanceNeverGoesUnderZero(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $t->tokens->grant($t->table, 'fate', $ana, 2, Table::gm(), 'op-1');

        $this->assertRefused('member:ana holds 2 "fate"; 3 cannot be spent nor given.', static fn (): Receipt => $t->tokens->spend($t->table, 'fate', $ana, 3, $ana, 'op-2'));
        self::assertSame(0, $t->tokens->spend($t->table, 'fate', $ana, 2, $ana, 'op-3')->balance());
        $this->assertRefused('member:ana holds 0 "fate"; 1 cannot be spent nor given.', static fn (): Receipt => $t->tokens->spend($t->table, 'fate', $ana, 1, $ana, 'op-4'));
    }

    public function testTheHolderOrAGameMasterSpends(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $t->tokens->grant($t->table, 'fate', $ana, 3, Table::gm(), 'op-1');

        $this->assertRefused('member:bo does not hold the tokens of member:ana, and is not a game master of the table.', static fn (): Receipt => $t->tokens->spend($t->table, 'fate', $ana, 1, Table::ref('member:bo'), 'op-2'));
        self::assertSame(2, $t->tokens->spend($t->table, 'fate', $ana, 1, Table::gm(), 'op-3')->balance());
    }

    public function testAPlayerSpendsTheTokensOfTheCharacterTheyPlay(): void
    {
        $access = new class implements HolderAccess {
            #[Override]
            public function controls(ResourceReference $actor, ResourceReference $holder): bool
            {
                return 'member:ana' === $actor->toString() && 'character:brom' === $holder->toString();
            }
        };
        $t = new Table(access: $access);
        $brom = Table::ref('character:brom');
        $t->tokens->grant($t->table, 'inspiration', $brom, 1, Table::gm(), 'op-1');

        $this->assertRefused('character:brom does not hold the tokens of character:brom, and is not a game master of the table.', static fn (): Receipt => $t->tokens->spend($t->table, 'inspiration', $brom, 1, $brom, 'op-2'));
        self::assertSame(0, $t->tokens->spend($t->table, 'inspiration', $brom, 1, Table::ref('member:ana'), 'op-3')->balance());
    }

    public function testTokensPassFromOneHolderToAnother(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');
        $t->tokens->grant($t->table, 'bennies', $ana, 3, Table::gm(), 'op-1');
        $t->tokens->grant($t->table, 'bennies', $bo, 3, Table::gm(), 'op-2');

        $receipt = $t->tokens->transfer($t->table, 'bennies', $ana, $bo, 2, $ana, 'op-3', 'For the brave');

        self::assertSame(
            [['transferred-out', 'member:ana', -2, 1, 3], ['transferred-in', 'member:bo', 2, 5, 4]],
            array_map(static fn (LedgerEntry $entry): array => [$entry->type->value, $entry->holder->toString(), $entry->change, $entry->balance, $entry->position], $receipt->entries),
        );
        self::assertSame(1, $receipt->balance());
        $event = $t->events->events[2];
        self::assertInstanceOf(TokensTransferred::class, $event);
        self::assertSame([$receipt->entries[0], $receipt->entries[1], 2], [$event->out, $event->in, $event->amount()]);

        self::assertTrue($t->tokens->transfer($t->table, 'bennies', $ana, $bo, 2, $ana, 'op-3')->replayed);
        $this->assertRefused('The operation op-3 was done already', static fn (): Receipt => $t->tokens->transfer($t->table, 'bennies', $ana, Table::ref('member:cy'), 2, $ana, 'op-3'));
        $this->assertRefused('member:bo holds 5 "bennies" of 5 at most; 1 more would go over.', static fn (): Receipt => $t->tokens->transfer($t->table, 'bennies', $ana, $bo, 1, $ana, 'op-4'));
        $this->assertRefused('member:ana holds 1 "bennies"; 2 cannot be spent nor given.', static fn (): Receipt => $t->tokens->transfer($t->table, 'bennies', $ana, Table::ref('member:cy'), 2, $ana, 'op-5'));
        $this->assertRefused('member:ana cannot pass tokens to themselves.', static fn (): Receipt => $t->tokens->transfer($t->table, 'bennies', $ana, $ana, 1, $ana, 'op-6'));
        $this->assertRefused('member:bo does not hold the tokens of member:ana', static fn (): Receipt => $t->tokens->transfer($t->table, 'bennies', $ana, Table::ref('member:cy'), 1, $bo, 'op-7'));
        $this->assertRefused('The token "fate" is not passed from one holder to another.', static fn (): Receipt => $t->tokens->transfer($t->table, 'fate', $ana, $bo, 1, Table::gm(), 'op-8'));
        self::assertSame(0, $t->tokens->transfer($t->table, 'bennies', $ana, Table::ref('member:cy'), 1, Table::gm(), 'op-9')->balance());
    }

    public function testASessionSetsTheTokensByCarryOverAndRefresh(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');
        $cy = Table::ref('member:cy');
        $t->tokens->grant($t->table, 'inspiration', $ana, 1, Table::gm(), 'op-1');
        $t->tokens->grant($t->table, 'bennies', $ana, 5, Table::gm(), 'op-2');
        $t->tokens->grant($t->table, 'fate', $bo, 7, Table::gm(), 'op-3');
        $t->tokens->grant($t->table, 'fate', $ana, 1, Table::gm(), 'op-4');

        $receipt = $t->tokens->startSession($t->table, 'session-2', [$cy, $ana], Table::gm(), 'op-5');

        self::assertNull($receipt->balance());
        self::assertSame(
            [
                ['session-started', null, 'member:gm', 0, 0],
                ['refreshed', 'bennies', 'member:ana', -2, 3],
                ['refreshed', 'bennies', 'member:bo', 3, 3],
                ['refreshed', 'bennies', 'member:cy', 3, 3],
                ['refreshed', 'fate', 'member:ana', 2, 3],
                ['refreshed', 'fate', 'member:cy', 3, 3],
            ],
            array_map(static fn (LedgerEntry $entry): array => [$entry->type->value, $entry->kind, $entry->holder->toString(), $entry->change, $entry->balance], $receipt->entries),
        );
        self::assertSame(['session-2'], array_unique(array_map(static fn (LedgerEntry $entry): ?string => $entry->session, $receipt->entries)));
        self::assertSame(1, $t->tokens->balance($t->table, 'inspiration', $ana), 'Inspiration is kept and never refreshed.');
        self::assertSame(7, $t->tokens->balance($t->table, 'fate', $bo), 'Fate points above the refresh are kept.');

        $event = $t->events->events[4];
        self::assertInstanceOf(SessionStarted::class, $event);
        self::assertSame($receipt->entries[0], $event->start);
        self::assertSame(\array_slice($receipt->entries, 1), $event->refreshes);

        self::assertTrue($t->tokens->startSession($t->table, 'session-2', [], Table::gm(), 'op-5')->replayed);
        $this->assertRefused('The session session-2 was started already, by another operation.', static fn (): Receipt => $t->tokens->startSession($t->table, 'session-2', [], Table::gm(), 'op-6'));
        $this->assertRefused('The operation op-5 was done already', static fn (): Receipt => $t->tokens->startSession($t->table, 'session-3', [], Table::gm(), 'op-5'));
        $this->assertRefused('Only a game master of the table starts a session; member:ana is not one.', static fn (): Receipt => $t->tokens->startSession($t->table, 'session-3', [], $ana, 'op-7'));

        $quiet = $t->tokens->startSession($t->table, 'session-3', [], Table::gm(), 'op-8');
        self::assertCount(1, $quiet->entries, 'Nothing changed: only the start is written.');
    }

    public function testASessionTakesAHundredHoldersGivenAtMost(): void
    {
        $t = new Table();
        $holders = array_map(static fn (int $index): ResourceReference => Table::ref('member:'.$index), range(1, 100));

        self::assertCount(201, $t->tokens->startSession($t->table, 's1', $holders, Table::gm(), 'op-1')->entries);
        $this->assertInvalid('A session starts with 100 holders given at most.', static fn (): Receipt => $t->tokens->startSession($t->table, 's2', [...$holders, Table::ref('member:x')], Table::gm(), 'op-2'));
        $this->assertInvalid('A session is named by 1 to 64 characters', static fn (): Receipt => $t->tokens->startSession($t->table, 'session two', [], Table::gm(), 'op-3'));
    }

    public function testTheBalancesAndTheLogAreReadForTheAudit(): void
    {
        $t = new Table();
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');
        $t->tokens->grant($t->table, 'bennies', $ana, 2, Table::gm(), 'op-1');
        $t->tokens->grant($t->table, 'fate', $bo, 1, Table::gm(), 'op-2');
        $t->tokens->spend($t->table, 'bennies', $ana, 2, $ana, 'op-3');
        $t->tokens->startSession($t->table, 's1', [], Table::gm(), 'op-4');

        self::assertSame(
            [['bennies', 'member:ana', 3], ['fate', 'member:bo', 3], ['bennies', 'member:bo', 3], ['fate', 'member:ana', 3]],
            array_map(static fn (Balance $balance): array => [$balance->kind, $balance->holder->toString(), $balance->amount], $t->tokens->balances($t->table)),
        );
        self::assertSame([1, 3, 5, 7], array_map(static fn (LedgerEntry $entry): int => $entry->position, $t->tokens->log($t->table, $ana)));
        self::assertSame([], $t->tokens->log($t->table, Table::gm()), 'The start of a session is no holding of its game master.');
        self::assertCount(8, $t->tokens->log($t->table));
        self::assertSame([], $t->tokens->balances(Table::ref('table:2')));
    }

    public function testATableWithoutRulesHandsOutNothing(): void
    {
        $t = new Table();
        $other = Table::ref('table:2');

        $this->assertRefused('The table table:2 has no rules for its tokens.', static fn (): Receipt => $t->tokens->grant($other, 'fate', Table::gm(), 1, Table::gm(), 'op-1'));
        $this->assertRefused('The table table:2 has no rules for its tokens.', static fn (): int => $t->tokens->balance($other, 'fate', Table::gm()));
        $this->assertRefused('The table hands out no token "luck".', static fn (): int => $t->tokens->balance($t->table, 'luck', Table::gm()));
        $this->assertRefused('The table hands out no token "luck".', static fn (): Receipt => $t->tokens->grant($t->table, 'luck', Table::gm(), 1, Table::gm(), 'op-2'));
        self::assertSame($t->rules->rulesFor($t->table), $t->tokens->rules($t->table));
    }

    public function testTheIdentifierAndTheReasonAreChecked(): void
    {
        $t = new Table();

        $this->assertInvalid('An operation is named by 1 to 64 characters among letters, digits, ".", "_", ":" and "-"; "op 1" is not.', static fn (): Receipt => $t->tokens->grant($t->table, 'fate', Table::gm(), 1, Table::gm(), 'op 1'));
        $this->assertInvalid('A reason is 1 to 280 characters, without control characters.', static fn (): Receipt => $t->tokens->grant($t->table, 'fate', Table::gm(), 1, Table::gm(), 'op-1', ' '));
        self::assertSame([], $t->tokens->log($t->table));
    }

    public function testAnotherWriterFirstMeansReadingAgain(): void
    {
        $inner = new InMemoryTokenLedger();
        $other = new Table($inner);
        $ana = Table::ref('member:ana');
        $other->tokens->grant($other->table, 'fate', $ana, 3, Table::gm(), 'op-1');
        $busy = new BusyLedger($inner, [
            static fn (): Receipt => $other->tokens->spend($other->table, 'fate', $ana, 1, $ana, 'other-1'),
            static fn (): Receipt => $other->tokens->spend($other->table, 'fate', $ana, 1, $ana, 'other-2'),
        ]);
        $t = new Table($busy);

        $receipt = $t->tokens->spend($t->table, 'fate', $ana, 1, $ana, 'op-2');

        self::assertSame([3, 4, 0], [$busy->appends, $receipt->entries[0]->position, $receipt->balance()]);
        self::assertCount(1, $t->events->events);
    }

    public function testTheCheckIsMadeAgainAfterAConflict(): void
    {
        $inner = new InMemoryTokenLedger();
        $other = new Table($inner);
        $ana = Table::ref('member:ana');
        $other->tokens->grant($other->table, 'fate', $ana, 1, Table::gm(), 'op-1');
        $t = new Table(new BusyLedger($inner, [static fn (): Receipt => $other->tokens->spend($other->table, 'fate', $ana, 1, $ana, 'other-1')]));

        $this->assertRefused('member:ana holds 0 "fate"; 1 cannot be spent nor given.', static fn (): Receipt => $t->tokens->spend($t->table, 'fate', $ana, 1, $ana, 'op-2'));
        self::assertSame(0, $t->tokens->balance($t->table, 'fate', $ana));
    }

    public function testAnOperationDoneByTheOtherWriterIsAReplay(): void
    {
        $inner = new InMemoryTokenLedger();
        $other = new Table($inner);
        $ana = Table::ref('member:ana');
        $t = new Table(new BusyLedger($inner, [static fn (): Receipt => $other->tokens->grant($other->table, 'fate', $ana, 1, Table::gm(), 'op-1')]));

        self::assertTrue($t->tokens->grant($t->table, 'fate', $ana, 1, Table::gm(), 'op-1')->replayed);
        self::assertSame(1, $t->tokens->balance($t->table, 'fate', $ana));
        self::assertSame([], $t->events->events);
    }

    public function testThreeConflictsInARowLetTheConflictThrough(): void
    {
        $inner = new InMemoryTokenLedger();
        $other = new Table($inner);
        $ana = Table::ref('member:ana');
        $grant = static fn (string $operation): Closure => static fn (): Receipt => $other->tokens->grant($other->table, 'bennies', Table::ref('member:'.$operation), 1, Table::gm(), $operation);
        $busy = new BusyLedger($inner, [$grant('a'), $grant('b'), $grant('c'), $grant('d')]);
        $t = new Table($busy);

        try {
            $t->tokens->grant($t->table, 'fate', $ana, 1, Table::gm(), 'op-1');
            self::fail('Three conflicts in a row.');
        } catch (LedgerConflict $ledgerConflict) {
            self::assertSame('The ledger of the table table:1 ends at position 3; entries came after position 2.', $ledgerConflict->getMessage());
        }

        self::assertSame(3, $busy->appends);
        self::assertSame([], $t->events->events);
    }

    public function testALedgerFullTakesNoMore(): void
    {
        $t = new Table(maxEntries: 3);
        $ana = Table::ref('member:ana');
        $bo = Table::ref('member:bo');
        $t->tokens->grant($t->table, 'bennies', $ana, 1, Table::gm(), 'op-1');
        $t->tokens->grant($t->table, 'bennies', $bo, 1, Table::gm(), 'op-2');

        $this->assertRefused('The ledger of the table table:1 holds 2 entries, the most it takes.', static fn (): Receipt => $t->tokens->transfer($t->table, 'bennies', $ana, $bo, 1, $ana, 'op-3'));
        self::assertSame(3, $t->tokens->grant($t->table, 'bennies', $ana, 1, Table::gm(), 'op-4')->entries[0]->position);
    }

    public function testWithoutADispatcherNothingIsTold(): void
    {
        $rules = new InMemoryTokenRules();
        $t = new Table();
        $rules->save($t->tokens->rules($t->table));
        $tokens = new LuckTokens($rules, new InMemoryTokenLedger());

        $receipt = $tokens->grant($t->table, 'fate', Table::gm(), 1, Table::gm(), 'op-1');

        self::assertSame(1, $receipt->balance());
        self::assertLessThanOrEqual(5, abs($receipt->entries[0]->at->getTimestamp() - time()));
        self::assertSame('UTC', $receipt->entries[0]->at->getTimezone()->getName());
    }

    /**
     * @param non-empty-string  $message
     * @param callable(): mixed $operation
     */
    private function assertRefused(string $message, callable $operation): void
    {
        try {
            $operation();
            self::fail('The operation is refused: '.$message);
        } catch (TokenRefused $tokenRefused) {
            self::assertStringStartsWith($message, $tokenRefused->getMessage());
        }
    }

    /**
     * @param non-empty-string  $message
     * @param callable(): mixed $operation
     */
    private function assertInvalid(string $message, callable $operation): void
    {
        try {
            $operation();
            self::fail('The value is refused: '.$message);
        } catch (InvalidTokenRule $invalidTokenRule) {
            self::assertStringStartsWith($message, $invalidTokenRule->getMessage());
        }
    }

    public function testTheEntryTypesOfAReceipt(): void
    {
        $t = new Table();

        self::assertSame(EntryType::Granted, $t->tokens->grant($t->table, 'fate', Table::gm(), 1, Table::gm(), 'op-1')->entries[0]->type);
    }
}
