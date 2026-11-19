<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure\Points;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\ExchangeBundle\Application\Contract\PointsHold;
use Tilttek\ExchangeBundle\Tests\Unit\Fixture\Clock;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Exception\InsufficientPoints;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;

/**
 * The contract every hold of points passes, on a member granted 100 points: an implementation extends this case and
 * builds itself on `$this->points` in `hold()`.
 */
abstract class PointsHoldTestCase extends TestCase
{
    protected Points $points;

    protected Currencies $currencies;

    protected Clock $clock;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new Clock('2026-11-07 12:00:00 UTC');
        $this->currencies = new Currencies(new Currency('points'), new Currency('gems'));
        $this->points = new Points(new InMemoryLedger(), $this->clock, $this->currencies);
        $this->points->grant(self::member(1), 100, 'welcome:1');
        $this->points->grant(self::member(1), 7, 'welcome:1:gems', currency: 'gems');
    }

    public function testAHoldTakesThePointsOutOfTheAvailableBalanceOnce(): void
    {
        $hold = $this->hold();

        self::assertTrue($hold->hold(self::reservation('a', 30)));
        self::assertFalse($hold->hold(self::reservation('a', 30)));
        self::assertSame(70, $hold->available(self::member(1), 'points'));
        self::assertSame(7, $hold->available(self::member(1), 'gems'));
        self::assertSame(0, $hold->available(self::member(2), 'points'));
    }

    public function testAHoldTheAvailableBalanceDoesNotCoverHoldsNothing(): void
    {
        $hold = $this->hold();
        $hold->hold(self::reservation('a', 80));

        try {
            $hold->hold(self::reservation('b', 21));
            self::fail('The balance was expected not to cover the hold.');
        } catch (InsufficientPoints) {
            self::assertSame(20, $hold->available(self::member(1), 'points'));
        }

        self::assertTrue($hold->hold(self::reservation('b', 20)));
        self::assertSame(0, $hold->available(self::member(1), 'points'));
    }

    public function testConfirmedPointsStaySpent(): void
    {
        $hold = $this->hold();
        $hold->hold(self::reservation('a', 30));

        $hold->confirm(self::reservation('a', 30));
        $hold->confirm(self::reservation('a', 30));
        $hold->confirm(self::reservation('never', 5));

        self::assertSame(0, $hold->held(self::member(1), 'points'));
        self::assertSame(70, $hold->available(self::member(1), 'points'));
        self::assertSame(70, $this->points->balance(self::member(1)));
    }

    public function testReleasedPointsComeBackOnce(): void
    {
        $hold = $this->hold();
        $hold->hold(self::reservation('a', 30));
        $hold->hold(self::reservation('b', 10));

        $hold->release(self::reservation('a', 30));
        $hold->release(self::reservation('a', 30));
        $hold->release(self::reservation('never', 5));

        self::assertSame(0, $hold->held(self::member(1), 'points'));
        self::assertSame(90, $hold->available(self::member(1), 'points'));
        self::assertSame(90, $this->points->balance(self::member(1)));
    }

    abstract protected function hold(): PointsHold;

    protected static function reservation(string $key, int $amount, string $currency = 'points'): Reservation
    {
        $at = new DateTimeImmutable('2026-11-07 12:00:00 UTC');

        return new Reservation(ExchangeKey::fromString($key), self::member(1), 'seat', 1, $amount, $currency, $at, $at->modify('+30 minutes'));
    }

    protected static function member(int $id): ResourceReference
    {
        return ResourceReference::of('member', (string) $id);
    }
}
