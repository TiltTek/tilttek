<?php

declare(strict_types=1);

namespace Tilttek\Points\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Points\Account;
use Tilttek\Points\Erasure;
use Tilttek\Points\Exception\InvalidPoints;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\Movement;
use Tilttek\Points\MovementKey;
use Tilttek\Points\MovementKind;
use Tilttek\Points\Recorded;
use Tilttek\Points\Tests\Unit\Ledger\LedgerTestCase;

#[CoversClass(MovementKey::class)]
#[CoversClass(MovementKind::class)]
#[CoversClass(Movement::class)]
#[CoversClass(LedgerEntry::class)]
#[CoversClass(Recorded::class)]
#[CoversClass(Erasure::class)]
final class MovementTest extends TestCase
{
    public function testKeys(): void
    {
        self::assertSame('order:1:redeem', MovementKey::fromString('order:1:redeem')->value);
        self::assertSame(400, \strlen(MovementKey::fromString(str_repeat('k', 400))->value));
        self::assertSame('#earn:games:game:1', MovementKey::derived('earn', 'games:game:1')->value);
        self::assertSame('a#b', MovementKey::decided('a#b')->value);
        self::assertTrue(MovementKey::fromString('a')->equals(MovementKey::fromString('a')));
        self::assertFalse(MovementKey::fromString('a')->equals(MovementKey::fromString('b')));

        foreach (['' => 0, str_repeat('k', 401) => 401] as $key => $length) {
            try {
                MovementKey::fromString($key);
                self::fail('A key of the wrong length was expected to be refused.');
            } catch (InvalidPoints $invalidPoints) {
                self::assertSame(\sprintf('A movement key is 1 to 400 bytes; this one is %d.', $length), $invalidPoints->getMessage());
            }
        }

        foreach (['a b', "a\tb", "a\x00b", "a\x7Fb", "a\nb"] as $key) {
            try {
                MovementKey::fromString($key);
                self::fail('A key with whitespace was expected to be refused.');
            } catch (InvalidPoints $invalidPoints) {
                self::assertSame('A movement key cannot contain whitespace or control characters.', $invalidPoints->getMessage());
            }
        }

        $this->expectExceptionObject(InvalidPoints::because('"#earn:x" starts with "#", which only the keys the library derives start with.'));
        MovementKey::decided('#earn:x');
    }

    public function testADecidedKeyIsCheckedLikeAnyOther(): void
    {
        $this->expectException(InvalidPoints::class);
        MovementKey::decided('a b');
    }

    public function testKinds(): void
    {
        self::assertSame([MovementKind::Earned, MovementKind::Reversed, MovementKind::Expired], MovementKind::derived());
        self::assertSame(
            ['earned' => true, 'reversed' => true, 'expired' => true, 'granted' => false, 'revoked' => false, 'spent' => false, 'refunded' => false, 'transferred' => false],
            array_combine(array_map(static fn (MovementKind $kind): string => $kind->value, MovementKind::cases()), array_map(static fn (MovementKind $kind): bool => $kind->isDerived(), MovementKind::cases())),
        );
    }

    public function testAMovement(): void
    {
        $movement = LedgerTestCase::movement('a', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 1_000_000_000_000_000, cause: 'c', reverses: 'r');

        self::assertSame(1_000_000_000_000_000, $movement->amount);
        self::assertSame(-1_000_000_000_000_000, $movement->amountFor(Account::fromString('member:member:1')));
        self::assertSame(1_000_000_000_000_000, $movement->amountFor(Account::fromString('redemption:reward:1')));
        self::assertSame(0, $movement->amountFor(Account::fromString('member:member:2')));
        self::assertSame(1, LedgerTestCase::movement('a', MovementKind::Spent, 'member:member:1', 'redemption:reward:1', 1)->amount);
    }

    public function testAmountsAreChecked(): void
    {
        foreach ([0, -1, 1_000_000_000_000_001] as $amount) {
            try {
                LedgerTestCase::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', $amount);
                self::fail('An amount out of bounds was expected to be refused.');
            } catch (InvalidPoints $invalidPoints) {
                self::assertSame(\sprintf('A movement moves 1 to 1000000000000000 points; this one moves %d.', $amount), $invalidPoints->getMessage());
            }
        }
    }

    public function testAMovementHasTwoSides(): void
    {
        $this->expectExceptionObject(InvalidPoints::because('A movement goes from one account to another; "member:member:1" is on both sides.'));
        LedgerTestCase::movement('a', MovementKind::Transferred, 'member:member:1', 'member:member:1', 1);
    }

    public function testTheCurrencyIsChecked(): void
    {
        $this->expectException(InvalidPoints::class);
        LedgerTestCase::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 1, currency: 'Points');
    }

    public function testTheSameMovement(): void
    {
        $movement = LedgerTestCase::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 1);

        self::assertTrue($movement->sameAs(LedgerTestCase::movement('b', MovementKind::Granted, 'issuance:manual', 'member:member:1', 1, '2027-01-01 00:00:00', cause: 'x', reverses: 'y')));
        self::assertFalse($movement->sameAs(LedgerTestCase::movement('a', MovementKind::Earned, 'issuance:manual', 'member:member:1', 1)));
        self::assertFalse($movement->sameAs(LedgerTestCase::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 1, currency: 'stars')));
        self::assertFalse($movement->sameAs(LedgerTestCase::movement('a', MovementKind::Granted, 'issuance:other', 'member:member:1', 1)));
        self::assertFalse($movement->sameAs(LedgerTestCase::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:2', 1)));
        self::assertFalse($movement->sameAs(LedgerTestCase::movement('a', MovementKind::Granted, 'issuance:manual', 'member:member:1', 2)));
    }

    public function testAnEntry(): void
    {
        $entry = new LedgerEntry(1, LedgerTestCase::movement('a', MovementKind::Reversed, 'member:member:1', 'issuance:games', 5, cause: 'game:1', reverses: '#earn:games:game:1'), new DateTimeImmutable('2026-11-07 12:00:00 UTC'));

        self::assertSame([
            'position' => 1,
            'key' => 'a',
            'kind' => 'reversed',
            'currency' => 'points',
            'debit' => 'member:member:1',
            'credit' => 'issuance:games',
            'amount' => 5,
            'occurred_at' => '2026-11-01T10:00:00+00:00',
            'written_at' => '2026-11-07T12:00:00+00:00',
            'cause' => 'game:1',
            'reverses' => '#earn:games:game:1',
        ], $entry->toArray());
        self::assertNull(new LedgerEntry(2, LedgerTestCase::movement('b', MovementKind::Granted, 'issuance:manual', 'member:member:1', 5), new DateTimeImmutable())->toArray()['reverses']);

        $recorded = new Recorded($entry, true);
        self::assertSame([$entry, true], [$recorded->entry, $recorded->new]);
        self::assertSame([0, 0], [new Erasure()->deleted, new Erasure()->detached]);
        self::assertSame([1, 2], [new Erasure(1, 2)->deleted, new Erasure(1, 2)->detached]);

        $this->expectExceptionObject(InvalidPoints::because('A position in the ledger starts at 1; 0 is not one.'));
        new LedgerEntry(0, $entry->movement, $entry->at);
    }
}
