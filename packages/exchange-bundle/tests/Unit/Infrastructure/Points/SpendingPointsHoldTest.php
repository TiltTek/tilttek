<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Tests\Unit\Infrastructure\Points;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\Redemptions;
use Tilttek\Exchange\Reward\InMemoryCatalog;
use Tilttek\Exchange\Reward\Reward;
use Tilttek\ExchangeBundle\Application\Contract\PointsHold;
use Tilttek\ExchangeBundle\Infrastructure\Points\SpendingPointsHold;
use Tilttek\Points\LedgerEntry;
use Tilttek\Points\MovementKind;

#[CoversClass(SpendingPointsHold::class)]
final class SpendingPointsHoldTest extends PointsHoldTestCase
{
    public function testAHoldIsTheSpendingOfTheReservationAndItsReleaseARefund(): void
    {
        $hold = $this->hold();
        $hold->hold(self::reservation('a', 30));
        $hold->release(self::reservation('a', 30));

        $moves = array_map(static fn (LedgerEntry $entry): array => [$entry->movement->kind, $entry->movement->key->value, $entry->movement->amount], \array_slice($this->points->history(self::member(1), 'points'), 1));
        self::assertSame([[MovementKind::Spent, 'exchange:reservation:a', 30], [MovementKind::Refunded, '#refund:exchange:reservation:a', 30]], $moves);
    }

    public function testAReservationOfRedemptionsAndAHoldOfTheSameKeyAreOneSpending(): void
    {
        $redemptions = new Redemptions(new InMemoryCatalog(new Reward('seat', 30)), new InMemoryReservationStore(), $this->points, $this->currencies, $this->clock);
        $hold = $this->hold();

        $reservation = $redemptions->reserve(self::member(1), 'seat', 'a');
        self::assertFalse($hold->hold($reservation));
        self::assertSame(70, $hold->available(self::member(1), 'points'));

        $hold->release($reservation);
        self::assertSame(100, $hold->available(self::member(1), 'points'));

        $redemptions->cancel('a');
        self::assertSame(100, $this->points->balance(self::member(1)));
    }

    #[Override]
    protected function hold(): PointsHold
    {
        return new SpendingPointsHold($this->points);
    }
}
