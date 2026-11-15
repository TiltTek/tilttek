<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\LuckTokens\Balance;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Ledger\EntryType;
use Tilttek\LuckTokens\Ledger\LedgerEntry;
use Tilttek\LuckTokens\LuckTokens;
use Tilttek\LuckTokens\Receipt;
use Tilttek\LuckTokens\Store\InMemoryTokenLedger;
use Tilttek\LuckTokens\TableRules;
use Tilttek\LuckTokens\Tests\Unit\Fixture\Table;
use Tilttek\LuckTokens\TokenKind;

/**
 * The order in which a call is checked and refused, and what a refusal quotes of the text it refuses: its first
 * characters — not bytes — so that a long or a multibyte text never fills a log nor splits a character in two.
 */
#[CoversClass(LedgerEntry::class)]
#[CoversClass(TableRules::class)]
#[CoversClass(TokenKind::class)]
#[CoversClass(LuckTokens::class)]
#[CoversClass(Receipt::class)]
#[CoversClass(InMemoryTokenLedger::class)]
final class RefusalsTest extends TestCase
{
    /**
     * @return iterable<string, array{Closure(string): mixed, string}>
     */
    public static function quoted(): iterable
    {
        yield 'an identifier' => [static fn (string $text): string => LedgerEntry::identifier($text, 'An operation'), 'An operation is named by 1 to 64 characters among letters, digits, ".", "_", ":" and "-"; "%s" is not.'];
        yield 'a key' => [static fn (string $text): TokenKind => new TokenKind($text, 'Fate', 3), 'The key of a token is 1 to 64 characters among a-z, 0-9 and inner "-"; "%s" is not.'];
        yield 'a field of the rules' => [static fn (string $text): TableRules => TableRules::fromArray(Table::ref('table:1'), [$text => 1]), 'The field "%s" of the rules is not one of kinds, game_masters.'];
        yield 'a field of a kind' => [static fn (string $text): TableRules => TableRules::fromArray(Table::ref('table:1'), ['kinds' => [['key' => 'fate', 'name' => 'Fate', 'cap' => 3, $text => 1]]]), 'The field "%s" of a kind of token is not one of key, name, cap, scope, refresh, refresh_to, carry_over, grants, transferable.'];
        yield 'the key in a message' => [static fn (string $text): TableRules => TableRules::fromArray(Table::ref('table:1'), ['kinds' => [['key' => $text, 'cap' => 3]]]), 'The field "name" of the token "%s" is a text.'];
    }

    /**
     * @param Closure(string): mixed $read
     */
    #[DataProvider('quoted')]
    public function testALongTextIsQuotedByItsFirstCharacters(Closure $read, string $message): void
    {
        foreach (['é', 'x'] as $character) {
            // Invalid as an identifier, a key and a field alike by its first character.
            $text = '!'.str_repeat($character, 39);

            try {
                $read($text);
                self::fail('The text should have been refused.');
            } catch (InvalidTokenRule $refused) {
                self::assertStringContainsString(\sprintf($message, mb_substr($text, 0, 32)), $refused->getMessage());
                self::assertTrue(mb_check_encoding($refused->getMessage(), 'UTF-8'));
            }
        }
    }

    public function testATextMayHoldCharactersWhoseBytesLookLikeControls(): void
    {
        // U+0100 is written C4 80 and U+0080 is a control character: only the second one is refused.
        self::assertSame('Ā', LedgerEntry::reason('Ā'));
        self::assertSame('Ā', new TokenKind('fate', 'Ā', 3)->name);

        try {
            LedgerEntry::reason("A\u{0080}");
            self::fail('A control character should have been refused.');
        } catch (InvalidTokenRule) {
            $this->expectException(InvalidTokenRule::class);
            new TokenKind('fate', "A\u{0080}", 3);
        }
    }

    public function testASessionIsRefusedBeforeWhoStartsIt(): void
    {
        $t = new Table();

        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains('A session is named by');

        $t->tokens->startSession($t->table, 'not a session', [], Table::ref('member:ana'), 'op-1');
    }

    public function testAnOperationIsRefusedBeforeWhatItAsks(): void
    {
        $t = new Table();

        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains('An operation is named by');

        $t->tokens->grant($t->table, 'unknown', Table::ref('member:ana'), 1, Table::gm(), 'not an operation');
    }

    public function testAReasonIsRefusedBeforeWhatItAsks(): void
    {
        $t = new Table();

        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains('A reason is 1 to 280 characters');

        $t->tokens->grant($t->table, 'unknown', Table::ref('member:ana'), 1, Table::gm(), 'op-1', '   ');
    }

    public function testTheHoldersGivenAreKeptByTheirFirstWriting(): void
    {
        $t = new Table();
        $first = Table::ref('member:ana');
        $again = Table::ref('member:ana');

        $started = $t->tokens->startSession($t->table, 's-1', [$first, $again], Table::gm(), 'op-1');

        $holders = array_filter(array_map(static fn (LedgerEntry $entry): ResourceReference => $entry->holder, \array_slice($started->entries, 1)), static fn (ResourceReference $holder): bool => $holder->equals($first));
        self::assertNotSame([], $holders);

        foreach ($holders as $holder) {
            self::assertSame($first, $holder);
        }
    }

    public function testTwoKindsAndHoldersWhoseNamesJoinTheSameStayApart(): void
    {
        $t = new Table();
        $t->rules->save(TableRules::fromJson($t->table, '{"kinds": [{"key": "a", "name": "A", "cap": 5}, {"key": "ab", "name": "AB", "cap": 5}], "game_masters": ["member:gm"]}'));

        $t->tokens->grant($t->table, 'a', Table::ref('bc:x'), 1, Table::gm(), 'op-1');
        $t->tokens->grant($t->table, 'ab', Table::ref('c:x'), 2, Table::gm(), 'op-2');

        $balances = $t->tokens->balances($t->table);
        self::assertCount(2, $balances);
        self::assertSame([['a', 'bc:x', 1], ['ab', 'c:x', 2]], array_map(static fn (Balance $balance): array => [$balance->kind, $balance->holder->toString(), $balance->amount], $balances));
    }

    public function testAReceiptWithoutEntriesHasNoBalance(): void
    {
        self::assertNull(new Receipt('op-1', [])->balance());
    }

    public function testTheLedgerKnowsASessionByItsStartAlone(): void
    {
        $ledger = new InMemoryTokenLedger();
        $table = Table::ref('table:1');
        $at = new DateTimeImmutable('2026-11-13 20:00 UTC');
        $ana = Table::ref('member:ana');

        $ledger->append($table, 0, [new LedgerEntry($table, 1, 'op-1', EntryType::Refreshed, 'fate', $ana, 3, 3, Table::gm(), $at, session: 's-9')]);
        self::assertFalse($ledger->hasSession($table, 's-9'));

        $ledger->append($table, 1, [new LedgerEntry($table, 2, 'op-2', EntryType::SessionStarted, null, Table::gm(), 0, 0, Table::gm(), $at, session: 's-1')]);
        self::assertTrue($ledger->hasSession($table, 's-1'));
        self::assertFalse($ledger->hasSession($table, 's-9'));
    }

    public function testTheLedgerKeepsTheFirstHolderItSawOfAName(): void
    {
        $ledger = new InMemoryTokenLedger();
        $table = Table::ref('table:1');
        $at = new DateTimeImmutable('2026-11-13 20:00 UTC');
        $first = Table::ref('member:ana');

        $ledger->append($table, 0, [
            new LedgerEntry($table, 1, 'op-1', EntryType::Granted, 'fate', $first, 1, 1, Table::gm(), $at),
            new LedgerEntry($table, 2, 'op-2', EntryType::Granted, 'fate', Table::ref('member:ana'), 1, 2, Table::gm(), $at),
        ]);

        self::assertSame([$first], $ledger->holders($table));
    }

    public function testTooManyKindsAreCountedBeforeTheyAreRead(): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains('A table hands out 16 kinds of tokens at most.');

        TableRules::fromArray(Table::ref('table:1'), ['kinds' => array_fill(0, 17, 'not a kind')]);
    }

    public function testTooManyGameMastersAreCountedBeforeTheyAreRead(): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains('A table has 16 game masters at most.');

        TableRules::fromArray(Table::ref('table:1'), ['game_masters' => array_fill(0, 17, 'not a reference')]);
    }

    public function testAKindIsNotTransferableUnlessItSaysSo(): void
    {
        $rules = TableRules::fromArray(Table::ref('table:1'), ['kinds' => [['key' => 'fate', 'name' => 'Fate', 'cap' => 3]]]);

        self::assertFalse($rules->kind('fate')->transferable);
    }
}
