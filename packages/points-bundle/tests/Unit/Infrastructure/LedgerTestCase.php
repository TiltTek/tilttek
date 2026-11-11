<?php

declare(strict_types=1);

namespace Tilttek\PointsBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Points\Account;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\Exception\MovementConflict;
use Tilttek\Points\Ledger\Ledger;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\Movement;
use Tilttek\Points\MovementKey;
use Tilttek\Points\MovementKind;

/**
 * The contract every ledger passes: a ledger of the application's storage extends this case and builds itself in
 * `ledger()`.
 */
abstract class LedgerTestCase extends TestCase
{
    abstract protected function ledger(): Ledger;

    public function testAMovementIsWrittenOnceByItsKey(): void
    {
        $ledger = $this->ledger();
        $first = $ledger->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 10), self::at('2026-11-07 12:00:00'));

        self::assertTrue($first->new);
        self::assertSame(1, $first->entry->position);
        self::assertSame('2026-11-07 12:00:00', $first->entry->at->format('Y-m-d H:i:s'));
        self::assertSame(['a', 'granted', 'points', 'issuance:manual', 'member:member:1', 10, '2026-11-01 10:00:00', null, null], self::values($first->entry));

        $again = $ledger->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 10, '2026-11-02 10:00:00'), self::at('2026-11-08 12:00:00'));
        self::assertFalse($again->new);
        self::assertSame(1, $again->entry->position);
        self::assertSame('2026-11-01 10:00:00', $again->entry->movement->occurredAt->format('Y-m-d H:i:s'));

        $second = $ledger->append(self::movement('b', MovementKind::Earned, 'issuance:games', 'member:member:1', 5, cause: 'game:1', reverses: 'x'), self::at('2026-11-07 12:00:00'));
        self::assertSame(2, $second->entry->position);
        self::assertSame(['b', 'earned', 'points', 'issuance:games', 'member:member:1', 5, '2026-11-01 10:00:00', 'game:1', 'x'], self::values($second->entry));
        self::assertSame(15, $ledger->balance(Account::fromString('member:member:1'), 'points'));
    }

    /**
     * @return iterable<string, array{Movement}>
     */
    public static function otherMovements(): iterable
    {
        yield 'kind' => [self::movement('a', MovementKind::Earned, 'issuance:manual', 'member:member:1', 10)];
        yield 'currency' => [self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 10, currency: 'stars')];
        yield 'debit' => [self::movement('a', MovementKind::Granted, 'issuance:other', 'member:member:1', 10)];
        yield 'credit' => [self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:2', 10)];
        yield 'amount' => [self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 11)];
    }

    #[DataProvider('otherMovements')]
    public function testAKeyWrittenForAnotherMovementIsAConflict(Movement $other): void
    {
        $ledger = $this->ledger();
        $ledger->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 10), self::at());

        try {
            $ledger->append($other, self::at());
            self::fail('A conflict was expected.');
        } catch (MovementConflict $movementConflict) {
            self::assertSame('The movement key "a" is already written for another movement.', $movementConflict->getMessage());
        }

        $this->expectException(MovementConflict::class);
        $ledger->appendCovered($other, self::at());
    }

    public function testACoveredMovementIsWrittenOnlyWhenTheBalanceCoversIt(): void
    {
        $ledger = $this->ledger();
        $member = Account::fromString('member:member:1');
        $ledger->append(self::movement('in', MovementKind::Granted, 'issuance:manual', 'member:member:1', 10), self::at());
        $ledger->append(self::movement('in-stars', MovementKind::Granted, 'issuance:manual', 'member:member:1', 50, currency: 'stars'), self::at());

        try {
            $ledger->appendCovered(self::movement('spend', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 11), self::at());
            self::fail('Insufficient points were expected.');
        } catch (InsufficientPoints $insufficientPoints) {
            self::assertSame('The account "member:member:1" holds 10 points; 11 cannot be paid from it.', $insufficientPoints->getMessage());
        }

        self::assertNull($ledger->find(MovementKey::fromString('spend')));
        $spent = $ledger->appendCovered(self::movement('spend', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 10), self::at('2026-11-07 13:00:00'));
        self::assertTrue($spent->new);
        self::assertSame(3, $spent->entry->position);
        self::assertSame('2026-11-07 13:00:00', $spent->entry->at->format('Y-m-d H:i:s'));
        self::assertSame(0, $ledger->balance($member, 'points'));

        $again = $ledger->appendCovered(self::movement('spend', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 10), self::at());
        self::assertFalse($again->new);
        self::assertSame(3, $again->entry->position);
        self::assertSame(0, $ledger->balance($member, 'points'));
    }

    public function testFindAndCause(): void
    {
        $ledger = $this->ledger();
        self::assertNull($ledger->find(MovementKey::fromString('a')));
        self::assertCount(0, $ledger->causedBy('game:1'));

        $ledger->append(self::movement('a', MovementKind::Earned, 'issuance:games', 'member:member:1', 5, cause: 'game:1'), self::at());
        $ledger->append(self::movement('b', MovementKind::Earned, 'issuance:games', 'member:member:2', 5, cause: 'game:2'), self::at());
        $ledger->append(self::movement('c', MovementKind::Earned, 'issuance:wins', 'member:member:1', 7, cause: 'game:1'), self::at());

        self::assertSame(2, $ledger->find(MovementKey::fromString('b'))?->position);
        self::assertSame([1, 3], self::positions($ledger->causedBy('game:1')));
    }

    public function testBalances(): void
    {
        $ledger = $this->ledger();
        $one = Account::fromString('member:member:1');
        $ledger->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 30), self::at());
        $ledger->append(self::movement('b', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 12), self::at());
        $ledger->append(self::movement('c', MovementKind::Transferred, 'member:member:2', 'member:member:1', 5), self::at());
        $ledger->append(self::movement('d', MovementKind::Granted, 'issuance:manual', 'member:member:1', 100, currency: 'stars'), self::at());

        self::assertSame(23, $ledger->balance($one, 'points'));
        self::assertSame(100, $ledger->balance($one, 'stars'));
        self::assertSame(-5, $ledger->balance(Account::fromString('member:member:2'), 'points'));
        self::assertSame(-30, $ledger->balance(Account::issuance('manual'), 'points'));
        self::assertSame(0, $ledger->balance(Account::fromString('member:member:3'), 'points'));
        self::assertSame(0, $ledger->balance($one, 'crowns'));
    }

    public function testNetBetweenTwoAccountsOverAPeriod(): void
    {
        $ledger = $this->ledger();
        $one = Account::fromString('member:member:1');
        $games = Account::issuance('games');
        $ledger->append(self::movement('a', MovementKind::Earned, 'issuance:games', 'member:member:1', 10, '2026-11-02 00:00:00'), self::at());
        $ledger->append(self::movement('b', MovementKind::Earned, 'issuance:games', 'member:member:1', 20, '2026-11-05 12:00:00'), self::at());
        $ledger->append(self::movement('c', MovementKind::Reversed, 'member:member:1', 'issuance:games', 4, '2026-11-05 12:00:00'), self::at());
        $ledger->append(self::movement('d', MovementKind::Earned, 'issuance:games', 'member:member:1', 40, '2026-11-09 00:00:00'), self::at());
        $ledger->append(self::movement('e', MovementKind::Earned, 'issuance:wins', 'member:member:1', 80, '2026-11-03 00:00:00'), self::at());
        $ledger->append(self::movement('f', MovementKind::Earned, 'issuance:games', 'member:member:2', 160, '2026-11-03 00:00:00'), self::at());
        $ledger->append(self::movement('g', MovementKind::Earned, 'issuance:games', 'member:member:1', 320, '2026-11-03 00:00:00', 'stars'), self::at());
        $ledger->append(self::movement('h', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 1, '2026-11-03 00:00:00'), self::at());

        self::assertSame(66, $ledger->net($one, $games, 'points'));
        self::assertSame(-66, $ledger->net($games, $one, 'points'));
        self::assertSame(26, $ledger->net($one, $games, 'points', self::at('2026-11-02 00:00:00'), self::at('2026-11-09 00:00:00')));
        self::assertSame(56, $ledger->net($one, $games, 'points', self::at('2026-11-02 00:00:01'), self::at('2026-11-09 00:00:01')));
        self::assertSame(56, $ledger->net($one, $games, 'points', self::at('2026-11-05 12:00:00')));
        self::assertSame(10, $ledger->net($one, $games, 'points', null, self::at('2026-11-05 12:00:00')));
        self::assertSame(320, $ledger->net($one, $games, 'stars'));
        self::assertSame(0, $ledger->net($one, $games, 'crowns'));
    }

    public function testAStatementReadsAnAccountInOrder(): void
    {
        $ledger = $this->ledger();
        $one = Account::fromString('member:member:1');
        $ledger->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 1), self::at());
        $ledger->append(self::movement('b', MovementKind::Granted, 'issuance:manual', 'member:member:2', 1), self::at());
        $ledger->append(self::movement('c', MovementKind::Granted, 'issuance:manual', 'member:member:1', 1, currency: 'stars'), self::at());
        $ledger->append(self::movement('d', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 1), self::at());
        $ledger->append(self::movement('e', MovementKind::Transferred, 'member:member:2', 'member:member:1', 1), self::at());

        self::assertSame([1, 3, 4, 5], self::positions($ledger->statement($one, null, 0, 10)));
        self::assertSame([1, 4, 5], self::positions($ledger->statement($one, 'points', 0, 10)));
        self::assertSame([4, 5], self::positions($ledger->statement($one, 'points', 1, 10)));
        self::assertSame([4], self::positions($ledger->statement($one, 'points', 1, 1)));
        self::assertSame([5], self::positions($ledger->statement($one, 'points', 4, 1)));
        self::assertSame([], self::positions($ledger->statement($one, 'points', 5, 10)));
        self::assertSame([2, 5], self::positions($ledger->statement(Account::fromString('member:member:2'), null, 0, 10)));
    }

    public function testTheBoundsOfAStatement(): void
    {
        $ledger = $this->ledger();

        try {
            $ledger->statement(Account::expiry(), null, -1, 1);
            self::fail('A negative position was expected to be refused.');
        } catch (InvalidPoints $invalidPoints) {
            self::assertSame('A read starts after a position of 0 or more; -1 is not one.', $invalidPoints->getMessage());
        }

        $this->expectExceptionObject(InvalidPoints::because('A read takes at least one entry; 0 is not a limit.'));
        $ledger->statement(Account::expiry(), null, 0, 0);
    }

    public function testTheMembersCreditedBeforeACutoff(): void
    {
        $ledger = $this->ledger();
        $ledger->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:2', 1, '2026-01-01 00:00:00'), self::at());
        $ledger->append(self::movement('b', MovementKind::Granted, 'issuance:manual', 'member:member:6', 1, '2026-02-01 00:00:00'), self::at());
        $ledger->append(self::movement('c', MovementKind::Granted, 'issuance:manual', 'member:member:1', 1, '2026-01-15 00:00:00'), self::at());
        $ledger->append(self::movement('d', MovementKind::Granted, 'issuance:manual', 'member:member:3', 1, '2026-02-01 00:00:01'), self::at());
        $ledger->append(self::movement('e', MovementKind::Granted, 'issuance:manual', 'member:member:4', 1, '2026-01-01 00:00:00', 'stars'), self::at());
        $ledger->append(self::movement('f', MovementKind::Revoked, 'member:member:5', 'issuance:manual', 1, '2026-01-01 00:00:00'), self::at());

        self::assertSame(['member:member:1', 'member:member:2', 'member:member:6'], array_map(static fn (Account $account): string => $account->toString(), $ledger->membersCreditedBefore('points', self::at('2026-02-01 00:00:00'))));
        self::assertSame(['member:member:4'], array_map(static fn (Account $account): string => $account->toString(), $ledger->membersCreditedBefore('stars', self::at('2026-02-01 00:00:00'))));
        self::assertSame([], $ledger->membersCreditedBefore('points', self::at('2025-12-31 23:59:59')));
    }

    public function testForgettingTheDerivedMovements(): void
    {
        $ledger = $this->ledger();

        foreach (MovementKind::cases() as $index => $kind) {
            $ledger->append(self::movement($kind->value.'-old', $kind, 'issuance:x', 'member:member:1', 1, '2026-11-01 00:00:00'), self::at());
            $ledger->append(self::movement($kind->value.'-new', $kind, 'issuance:x', 'member:member:1', 1, '2026-11-05 00:00:00'), self::at());
            self::assertSame(($index + 1) * 2, $ledger->balance(Account::fromString('member:member:1'), 'points'));
        }

        self::assertSame(3, $ledger->forgetDerived(self::at('2026-11-05 00:00:00')));
        self::assertNull($ledger->find(MovementKey::fromString('earned-new')));
        self::assertNull($ledger->find(MovementKey::fromString('reversed-new')));
        self::assertNull($ledger->find(MovementKey::fromString('expired-new')));
        self::assertNotNull($ledger->find(MovementKey::fromString('earned-old')));
        self::assertNotNull($ledger->find(MovementKey::fromString('granted-new')));
        self::assertSame(13, $ledger->balance(Account::fromString('member:member:1'), 'points'));

        self::assertSame(3, $ledger->forgetDerived(null));
        self::assertNull($ledger->find(MovementKey::fromString('earned-old')));
        self::assertSame(10, $ledger->balance(Account::fromString('member:member:1'), 'points'));
        self::assertSame(0, $ledger->forgetDerived(null));
    }

    public function testErasingAMember(): void
    {
        $ledger = $this->ledger();
        $ledger->append(self::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 30), self::at());
        $ledger->append(self::movement('b', MovementKind::Transferred, 'member:member:1', 'member:member:2', 10), self::at());
        $ledger->append(self::movement('x', MovementKind::Granted, 'issuance:manual', 'member:member:3', 1), self::at());
        $ledger->append(self::movement('c', MovementKind::Transferred, 'member:member:2', 'member:member:1', 4), self::at());
        $ledger->append(self::movement('d', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 3), self::at());
        $ledger->append(self::movement('e', MovementKind::Granted, 'issuance:manual', 'member:member:2', 7, cause: 'k', reverses: 'r'), self::at());

        $erasure = $ledger->eraseMember(ResourceReference::of('member', '1'));
        self::assertSame([2, 2], [$erasure->deleted, $erasure->detached]);
        self::assertNull($ledger->find(MovementKey::fromString('a')));
        self::assertNull($ledger->find(MovementKey::fromString('d')));
        self::assertSame(['b', 'transferred', 'points', 'erased:member', 'member:member:2', 10, '2026-11-01 10:00:00', null, null], self::values($ledger->find(MovementKey::fromString('b'))));
        self::assertSame(['c', 'transferred', 'points', 'member:member:2', 'erased:member', 4, '2026-11-01 10:00:00', null, null], self::values($ledger->find(MovementKey::fromString('c'))));
        self::assertSame(['e', 'granted', 'points', 'issuance:manual', 'member:member:2', 7, '2026-11-01 10:00:00', 'k', 'r'], self::values($ledger->find(MovementKey::fromString('e'))));
        self::assertSame(2, $ledger->find(MovementKey::fromString('b'))?->position);
        self::assertSame(13, $ledger->balance(Account::fromString('member:member:2'), 'points'));
        self::assertSame(0, $ledger->balance(Account::fromString('member:member:1'), 'points'));
        self::assertSame([2, 4, 6], self::positions($ledger->statement(Account::fromString('member:member:2'), null, 0, 10)));

        $again = $ledger->eraseMember(ResourceReference::of('member', '2'));
        self::assertSame([3, 0], [$again->deleted, $again->detached]);
        self::assertSame(1, $ledger->balance(Account::fromString('member:member:3'), 'points'));
        self::assertSame(7, $ledger->append(self::movement('f', MovementKind::Granted, 'issuance:manual', 'member:member:3', 1), self::at())->entry->position);
    }

    public static function movement(string $key, MovementKind $kind, string $debit, string $credit, int $amount, string $occurredAt = '2026-11-01 10:00:00', string $currency = 'points', ?string $cause = null, ?string $reverses = null): Movement
    {
        return new Movement(
            MovementKey::fromString($key),
            $kind,
            $currency,
            Account::fromString($debit),
            Account::fromString($credit),
            $amount,
            self::at($occurredAt),
            $cause,
            null === $reverses ? null : MovementKey::fromString($reverses),
        );
    }

    protected static function at(string $moment = '2026-11-07 12:00:00'): DateTimeImmutable
    {
        return new DateTimeImmutable($moment.' UTC');
    }

    /**
     * @param list<LedgerEntry> $entries
     *
     * @return list<int>
     */
    protected static function positions(array $entries): array
    {
        return array_map(static fn (LedgerEntry $entry): int => $entry->position, $entries);
    }

    /**
     * @return list<int|string|null>
     */
    protected static function values(?LedgerEntry $entry): array
    {
        self::assertInstanceOf(LedgerEntry::class, $entry);
        $movement = $entry->movement;

        return [
            $movement->key->value,
            $movement->kind->value,
            $movement->currency,
            $movement->debit->toString(),
            $movement->credit->toString(),
            $movement->amount,
            $movement->occurredAt->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s'),
            $movement->cause,
            $movement->reverses?->value,
        ];
    }
}
