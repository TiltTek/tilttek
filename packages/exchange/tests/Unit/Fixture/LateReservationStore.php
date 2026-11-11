<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Reservation\ReservationStatus;
use Tilttek\Exchange\Reservation\ReservationStore;

/**
 * A store where another process writes its reservation under the same key just before this one.
 */
final readonly class LateReservationStore implements ReservationStore
{
    private InMemoryReservationStore $inner;

    public function __construct(
        private Reservation $competitor,
    ) {
        $this->inner = new InMemoryReservationStore();
    }

    #[Override]
    public function add(Reservation $reservation, ?int $stock, ?int $perMember): bool
    {
        $this->inner->add($this->competitor, null, null);

        return $this->inner->add($reservation, $stock, $perMember);
    }

    #[Override]
    public function find(ExchangeKey $key): ?Reservation
    {
        return $this->inner->find($key);
    }

    #[Override]
    public function settle(ExchangeKey $key, ReservationStatus $status, DateTimeImmutable $at): bool
    {
        return $this->inner->settle($key, $status, $at);
    }

    #[Override]
    public function discard(ExchangeKey $key): void
    {
        $this->inner->discard($key);
    }

    #[Override]
    public function held(string $reward, ?ResourceReference $member = null): int
    {
        return $this->inner->held($reward, $member);
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        return $this->inner->due($now, $limit);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->inner->ofMember($member);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return $this->inner->eraseMember($member);
    }
}
