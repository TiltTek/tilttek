<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Holdings;

#[CoversClass(Holdings::class)]
final class HoldingsTest extends TestCase
{
    public function testNoHoldingsOwnNothing(): void
    {
        self::assertSame(0, Holdings::none()->count());
        self::assertSame(0, Holdings::none()->levelOf('a'));
    }

    public function testMembersGivenTwiceAreOwnedOnceAtLevelOne(): void
    {
        $holdings = Holdings::of((static function (): iterable {
            yield 'a';
            yield '42';
            yield 'a';
        })());

        self::assertSame(2, $holdings->count());
        self::assertSame(1, $holdings->levelOf('a'));
        self::assertSame(1, $holdings->levelOf('42'));
        self::assertSame(0, $holdings->levelOf('b'));
    }

    public function testLevelsAreKeptAndLevelZeroIsNotOwned(): void
    {
        $holdings = Holdings::withLevels(['a' => 3, 'b' => 0, 7 => 1, 'c' => 1000]);

        self::assertSame(3, $holdings->count());
        self::assertSame(3, $holdings->levelOf('a'));
        self::assertSame(0, $holdings->levelOf('b'));
        self::assertSame(1, $holdings->levelOf('7'));
        self::assertSame(1000, $holdings->levelOf('c'));
    }

    public function testANegativeLevelIsRefused(): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('A member is owned at a level of 0 to 1000; "a" is at -1.');

        Holdings::withLevels(['a' => -1]);
    }

    public function testALevelAboveTheBoundIsRefused(): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('A member is owned at a level of 0 to 1000; "a" is at 1001.');

        Holdings::withLevels(['a' => 1001]);
    }

    public function testAMemberOutOfShapeIsRefused(): void
    {
        $this->expectException(InvalidSet::class);

        Holdings::withLevels(['a b' => 1]);
    }

    public function testAMemberOutOfShapeIsRefusedInAList(): void
    {
        $this->expectException(InvalidSet::class);

        Holdings::of(['someone@example.org']);
    }

    public function testTheNumberOfMembersIsBounded(): void
    {
        $members = array_map(static fn (int $member): string => 'm'.$member, range(1, Holdings::MAX_ENTRIES));

        self::assertSame(Holdings::MAX_ENTRIES, Holdings::of([...$members, 'm1'])->count());
        self::assertSame(Holdings::MAX_ENTRIES, Holdings::withLevels([...array_fill_keys($members, 2), 'zero' => 0])->count());

        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('Holdings hold at most 100000 members.');

        Holdings::of([...$members, 'one-more']);
    }

    public function testTheNumberOfLeveledMembersIsBounded(): void
    {
        $members = array_map(static fn (int $member): string => 'm'.$member, range(0, Holdings::MAX_ENTRIES));

        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains('Holdings hold at most 100000 members.');

        Holdings::withLevels(array_fill_keys($members, 1));
    }
}
