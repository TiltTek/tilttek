<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Reward;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\Exception\InvalidExchange;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Reward\Reward;
use Tilttek\Points\Movement;

#[CoversClass(Reward::class)]
#[CoversClass(InvalidExchange::class)]
final class RewardTest extends TestCase
{
    public function testARewardFromItsConfiguration(): void
    {
        $reward = Reward::fromArray('seat.2026-spring', [
            'price' => 250,
            'currency' => 'stars',
            'stock' => 30,
            'available_from' => '2026-11-01 00:00:00 UTC',
            'available_until' => '2026-12-01 00:00:00 UTC',
            'per_member' => 2,
            'requires' => ['badge:helper', 'level:5'],
        ]);

        self::assertSame('seat.2026-spring', $reward->code);
        self::assertSame(250, $reward->price);
        self::assertSame('stars', $reward->currency);
        self::assertSame(30, $reward->stock);
        self::assertSame('2026-11-01 00:00:00', $reward->availableFrom?->format('Y-m-d H:i:s'));
        self::assertSame('2026-12-01 00:00:00', $reward->availableUntil?->format('Y-m-d H:i:s'));
        self::assertSame(2, $reward->perMember);
        self::assertSame(['badge:helper', 'level:5'], $reward->requires);

        $plain = Reward::fromArray('discount-10', ['price' => 100]);
        self::assertNull($plain->currency);
        self::assertNull($plain->stock);
        self::assertNull($plain->availableFrom);
        self::assertNull($plain->availableUntil);
        self::assertNull($plain->perMember);
        self::assertSame([], $plain->requires);
        self::assertSame(0, new Reward('sold-out', 1, stock: 0)->stock);
        self::assertSame(1, new Reward('one', 1, perMember: 1)->perMember);
        self::assertSame(Movement::MAX_AMOUNT, new Reward('dear', Movement::MAX_AMOUNT)->price);
        self::assertSame([str_repeat('r', 255)], new Reward('r', 1, requires: [str_repeat('r', 255)])->requires);
        self::assertSame(str_repeat('a', 100), new Reward(str_repeat('a', 100), 1)->code);
    }

    public function testTheAvailabilityFollowsThePeriod(): void
    {
        $reward = new Reward('seat', 10, availableFrom: new DateTimeImmutable('2026-11-01 00:00:00 UTC'), availableUntil: new DateTimeImmutable('2026-12-01 00:00:00 UTC'));

        self::assertSame(Refusal::NotYetAvailable, $reward->availability(new DateTimeImmutable('2026-10-31 23:59:59 UTC')));
        self::assertNull($reward->availability(new DateTimeImmutable('2026-11-01 00:00:00 UTC')));
        self::assertNull($reward->availability(new DateTimeImmutable('2026-11-30 23:59:59 UTC')));
        self::assertSame(Refusal::NoLongerAvailable, $reward->availability(new DateTimeImmutable('2026-12-01 00:00:00 UTC')));
        self::assertNull(new Reward('always', 1)->availability(new DateTimeImmutable('2000-01-01')));
        self::assertNull(new Reward('from', 1, availableFrom: new DateTimeImmutable('2026-11-01 00:00:00 UTC'))->availability(new DateTimeImmutable('2099-01-01')));
        self::assertNull(new Reward('until', 1, availableUntil: new DateTimeImmutable('2026-11-01 00:00:00 UTC'))->availability(new DateTimeImmutable('2000-01-01')));
    }

    public function testTheCostOfUnits(): void
    {
        $reward = new Reward('seat', 250);

        self::assertSame(250, $reward->cost(1));
        self::assertSame(750, $reward->cost(3));
        self::assertSame(Movement::MAX_AMOUNT, new Reward('half', 2)->cost(intdiv(Movement::MAX_AMOUNT, 2)));
    }

    public function testACostPastAMovementIsRefused(): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('%d units of "half" cost more points than one movement moves.', intdiv(Movement::MAX_AMOUNT, 2) + 1));

        new Reward('half', 2)->cost(intdiv(Movement::MAX_AMOUNT, 2) + 1);
    }

    public function testAQuantityIsOneOrMore(): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains('A reservation holds at least one unit; 0 is not a quantity.');

        new Reward('seat', 1)->cost(0);
    }

    /**
     * @return iterable<string, array{callable(): Reward, string}>
     */
    public static function refused(): iterable
    {
        yield 'code' => [static fn (): Reward => new Reward('Seat', 1), '"Seat" is not a reward code: 1 to 100 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit.'];
        yield 'code start' => [static fn (): Reward => new Reward('-seat', 1), '"-seat" is not a reward code'];
        yield 'code length' => [static fn (): Reward => new Reward(str_repeat('a', 101), 1), 'is not a reward code'];
        yield 'code line' => [static fn (): Reward => new Reward("seat\n", 1), 'is not a reward code'];
        yield 'price' => [static fn (): Reward => new Reward('seat', 0), \sprintf('A reward costs 1 to %d points; "seat" costs 0.', Movement::MAX_AMOUNT)];
        yield 'price too high' => [static fn (): Reward => new Reward('seat', Movement::MAX_AMOUNT + 1), \sprintf('"seat" costs %d.', Movement::MAX_AMOUNT + 1)];
        yield 'currency' => [static fn (): Reward => new Reward('seat', 1, 'Stars'), '"Stars" is not a currency code'];
        yield 'stock' => [static fn (): Reward => new Reward('seat', 1, stock: -1), 'A stock holds 0 units or more; "seat" holds -1.'];
        yield 'limit' => [static fn (): Reward => new Reward('seat', 1, perMember: 0), 'A member may hold at least one unit of a reward; 0 is not a limit.'];
        yield 'period' => [static fn (): Reward => new Reward('seat', 1, availableFrom: new DateTimeImmutable('2026-11-02'), availableUntil: new DateTimeImmutable('2026-11-01')), 'The availability of "seat" ends before it starts.'];
        yield 'empty period' => [static fn (): Reward => new Reward('seat', 1, availableFrom: new DateTimeImmutable('2026-11-02'), availableUntil: new DateTimeImmutable('2026-11-02')), 'ends before it starts'];
        yield 'requirement' => [static fn (): Reward => new Reward('seat', 1, requires: ['']), 'A requirement is 1 to 255 bytes.'];
        yield 'long requirement' => [static fn (): Reward => new Reward('seat', 1, requires: [str_repeat('r', 256)]), 'A requirement is 1 to 255 bytes.'];
        yield 'date' => [static fn (): Reward => Reward::fromArray('seat', ['price' => 1, 'available_from' => 'soon-ish']), '"soon-ish" is not a date.'];
    }

    /**
     * @param callable(): Reward $build
     */
    #[DataProvider('refused')]
    public function testARewardIsRefused(callable $build, string $message): void
    {
        $this->expectException(InvalidExchange::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }
}
