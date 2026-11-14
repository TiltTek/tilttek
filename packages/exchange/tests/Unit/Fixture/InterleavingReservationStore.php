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
 * A store where another process acts once, right after a reservation is added or right before the first change of a
 * status is written: the step that slips between a read or a payment and the write that follows.
 */
final class InterleavingReservationStore implements ReservationStore
{
    /**
     * @var ?callable(Reservation): void
     */
    private $meanwhile;

    /**
     * @param 'add'|'transition'          $when
     * @param callable(Reservation): void $meanwhile
     */
    public function __construct(
        private readonly ReservationStore $inner,
        private readonly string $when,
        callable $meanwhile,
    ) {
        $this->meanwhile = $meanwhile;
    }

    #[Override]
    public function add(Reservation $reservation, ?int $stock, ?int $perMember): bool
    {
        $added = $this->inner->add($reservation, $stock, $perMember);

        if ($added) {
            $this->meanwhile('add', $reservation);
        }

        return $added;
    }

    #[Override]
    public function find(ExchangeKey $key): ?Reservation
    {
        return $this->inner->find($key);
    }

    #[Override]
    public function transition(ExchangeKey $key, ReservationStatus $from, ReservationStatus $to, DateTimeImmutable $at): bool
    {
        $this->meanwhile('transition', $this->inner->find($key));

        return $this->inner->transition($key, $from, $to, $at);
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

    private function meanwhile(string $when, ?Reservation $reservation): void
    {
        if ($when === $this->when && $reservation instanceof Reservation && null !== $this->meanwhile) {
            $meanwhile = $this->meanwhile;
            $this->meanwhile = null;
            $meanwhile($reservation);
        }
    }
}
