<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Reservation\ReservationStatus;
use Tilttek\Exchange\Reservation\ReservationStore;

/**
 * A store whose due reservations are always settled by another process first.
 */
final readonly class SettledElsewhereStore implements ReservationStore
{
    public function __construct(
        private Reservation $due,
    ) {
    }

    #[Override]
    public function add(Reservation $reservation, ?int $stock, ?int $perMember): bool
    {
        return false;
    }

    #[Override]
    public function find(ExchangeKey $key): ?Reservation
    {
        return null;
    }

    #[Override]
    public function settle(ExchangeKey $key, ReservationStatus $status, DateTimeImmutable $at): bool
    {
        return false;
    }

    #[Override]
    public function discard(ExchangeKey $key): void
    {
    }

    #[Override]
    public function held(string $reward, ?ResourceReference $member = null): int
    {
        return 0;
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        return [$this->due];
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return [];
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return 0;
    }
}
