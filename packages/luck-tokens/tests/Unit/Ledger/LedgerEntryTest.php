<?php

declare(strict_types=1);

namespace Tilttek\LuckTokens\Tests\Unit\Ledger;

use Closure;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\LuckTokens\Exception\InvalidTokenRule;
use Tilttek\LuckTokens\Instant;
use Tilttek\LuckTokens\Ledger\EntryType;
use Tilttek\LuckTokens\Ledger\LedgerEntry;
use Tilttek\LuckTokens\Tests\Unit\Fixture\Table;

#[CoversClass(LedgerEntry::class)]
#[CoversClass(EntryType::class)]
#[CoversClass(Instant::class)]
final class LedgerEntryTest extends TestCase
{
    public function testAnEntryIsWrittenInUtcAndReadBack(): void
    {
        $entry = self::entry(at: new DateTimeImmutable('2026-11-13 22:15:03.25 America/Toronto'), reason: 'Brave', session: 's-1');

        self::assertSame('UTC', $entry->at->getTimezone()->getName());
        $row = $entry->toArray();
        self::assertSame('2026-11-14T03:15:03.250000Z', $row['at']);
        self::assertEquals($entry, LedgerEntry::fromArray($row));
        $start = self::entry(type: EntryType::SessionStarted, kind: null, change: 0, balance: 0, session: 's-1');
        self::assertEquals($start, LedgerEntry::fromArray($start->toArray()));
    }

    public function testTheIntentLeavesOutTheSignTheReasonAndTheTime(): void
    {
        self::assertSame('spent|fate|member:ana|2|member:gm|', self::entry(type: EntryType::Spent, change: -2, reason: 'x')->intent());
        self::assertSame('session-started||member:gm|0|member:gm|s-1', LedgerEntry::intentOf(EntryType::SessionStarted, null, Table::gm(), 0, Table::gm(), 's-1'));
    }

    public function testTheIdentifiersAndTheReasonAtTheirLimits(): void
    {
        self::assertSame(str_repeat('A', 64), LedgerEntry::identifier(str_repeat('A', 64), 'An operation'));
        self::assertSame('a.b_c:d-9', LedgerEntry::identifier('a.b_c:d-9', 'An operation'));
        self::assertSame(str_repeat('é', 280), LedgerEntry::reason(str_repeat('é', 280)));
        self::assertSame(1, self::entry(position: 1)->position);
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function refused(): iterable
    {
        $mixed = 'An entry names its kind of token, except the start of a session, which names its session instead.';
        yield 'a grant without kind' => [static fn (): LedgerEntry => self::entry(kind: null), $mixed];
        yield 'a start with a kind' => [static fn (): LedgerEntry => self::entry(type: EntryType::SessionStarted, session: 's-1'), $mixed];
        yield 'a start without session' => [static fn (): LedgerEntry => self::entry(type: EntryType::SessionStarted, kind: null), $mixed];
        yield 'position 0' => [static fn (): LedgerEntry => self::entry(position: 0), 'The position of a ledger entry is 1 or more; not 0.'];
        yield 'a negative balance' => [static fn (): LedgerEntry => self::entry(balance: -1), 'A balance is never negative; not -1.'];
        yield 'an empty operation' => [static fn (): LedgerEntry => self::entry(operation: ''), 'An operation is named by 1 to 64 characters among letters, digits, ".", "_", ":" and "-"; "" is not.'];
        yield 'an operation too long' => [static fn (): LedgerEntry => self::entry(operation: str_repeat('a', 65)), '"'.str_repeat('a', 32).'" is not.'];
        yield 'an operation with a new line' => [static fn (): LedgerEntry => self::entry(operation: "op\n"), 'An operation is named by'];
        yield 'a session with a space' => [static fn (): LedgerEntry => self::entry(session: 's 1'), 'A session is named by 1 to 64 characters'];
        yield 'a blank reason' => [static fn (): LedgerEntry => self::entry(reason: '  '), 'A reason is 1 to 280 characters, without control characters.'];
        yield 'a reason too long' => [static fn (): string => LedgerEntry::reason(str_repeat('é', 281)), 'A reason is 1 to 280 characters'];
        yield 'a reason with a control' => [static fn (): string => LedgerEntry::reason("a\u{0}b"), 'A reason is 1 to 280 characters'];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('refused')]
    public function testWhatIsNotAnEntryIsRefused(Closure $build, string $message): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedRows(): iterable
    {
        $row = self::entry()->toArray();
        $fields = 'A ledger entry has the fields table, position, operation, type, kind, holder, change, balance, actor, at, reason, session, in that order.';
        $types = 'A ledger entry holds a type, integers for its position, change and balance, texts for its operation and time, and a text or null for its kind, reason and session.';

        yield 'a field missing' => [array_diff_key($row, ['session' => true]), $fields];
        yield 'a field more' => [[...$row, 'extra' => 1], $fields];
        yield 'fields out of order' => [['position' => 1, ...$row], $fields];
        yield 'a type not known' => [[...$row, 'type' => 'stolen'], $types];
        yield 'a type not a text' => [[...$row, 'type' => 1], $types];
        yield 'a position as text' => [[...$row, 'position' => '1'], $types];
        yield 'a change as text' => [[...$row, 'change' => '1'], $types];
        yield 'a balance as text' => [[...$row, 'balance' => '1'], $types];
        yield 'an operation not a text' => [[...$row, 'operation' => 1], $types];
        yield 'a time not a text' => [[...$row, 'at' => 1], $types];
        yield 'a kind not a text' => [[...$row, 'kind' => 1], $types];
        yield 'a reason not a text' => [[...$row, 'reason' => 1], $types];
        yield 'a session not a text' => [[...$row, 'session' => 1], $types];
        yield 'a table not a reference' => [[...$row, 'table' => 'table'], '"table" is not a resource reference of the form "kind:identifier".'];
        yield 'a table not a text' => [[...$row, 'table' => 1], '"" is not a resource reference'];
        yield 'a holder not a reference' => [[...$row, 'holder' => 'ana'], '"ana" is not a resource reference'];
        yield 'an actor not a reference' => [[...$row, 'actor' => 'Gm:1'], 'A resource kind is 1 to 64'];
        yield 'a time not in UTC' => [[...$row, 'at' => '2026-11-13T22:15:03.250000+01:00'], 'A time is written in UTC to the microsecond, as 2026-11-13T20:15:03.250000Z.'];
        yield 'a time without microseconds' => [[...$row, 'at' => '2026-11-13T22:15:03Z'], 'A time is written in UTC'];
        yield 'a time that does not exist' => [[...$row, 'at' => '2026-02-30T22:15:03.250000Z'], 'A time is written in UTC'];
    }

    /**
     * @param array<string, mixed> $row
     */
    #[DataProvider('refusedRows')]
    public function testARowNotWrittenByAnEntryIsRefused(array $row, string $message): void
    {
        $this->expectException(InvalidTokenRule::class);
        $this->expectExceptionMessageIsOrContains($message);

        LedgerEntry::fromArray($row);
    }

    private static function entry(
        int $position = 1,
        string $operation = 'op-1',
        EntryType $type = EntryType::Granted,
        ?string $kind = 'fate',
        int $change = 2,
        int $balance = 2,
        DateTimeImmutable $at = new DateTimeImmutable('2026-11-13 20:15:03.25 UTC'),
        ?string $reason = null,
        ?string $session = null,
    ): LedgerEntry {
        return new LedgerEntry(Table::ref('table:1'), $position, $operation, $type, $kind, Table::ref('member:ana'), $change, $balance, Table::gm(), $at, $reason, $session);
    }
}
