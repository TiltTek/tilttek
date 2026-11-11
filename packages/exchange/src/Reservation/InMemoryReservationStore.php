<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Reservation;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Refusal;

/**
 * The reservations in memory, for tests and for applications that keep nothing between requests.
 */
final class InMemoryReservationStore implements ReservationStore
{
    /**
     * @var array<string, Reservation>
     */
    private array $reservations = [];

    #[Override]
    public function add(Reservation $reservation, ?int $stock, ?int $perMember): bool
    {
        if (isset($this->reservations[$reservation->key->value])) {
            return false;
        }

        if (null !== $stock && $this->held($reservation->reward) + $reservation->quantity > $stock) {
            throw ExchangeRefused::because(Refusal::OutOfStock, \sprintf('The reward "%s" has not %d units left.', $reservation->reward, $reservation->quantity));
        }

        if (null !== $perMember && $this->held($reservation->reward, $reservation->member) + $reservation->quantity > $perMember) {
            throw ExchangeRefused::because(Refusal::MemberLimitReached, \sprintf('A member holds at most %d units of "%s".', $perMember, $reservation->reward));
        }

        $this->reservations[$reservation->key->value] = $reservation;

        return true;
    }

    #[Override]
    public function find(ExchangeKey $key): ?Reservation
    {
        return $this->reservations[$key->value] ?? null;
    }

    #[Override]
    public function settle(ExchangeKey $key, ReservationStatus $status, DateTimeImmutable $at): bool
    {
        $reservation = $this->find($key);

        if (ReservationStatus::Reserved !== $reservation?->status) {
            return false;
        }

        $this->reservations[$key->value] = $reservation->settled($status, $at);

        return true;
    }

    #[Override]
    public function discard(ExchangeKey $key): void
    {
        if (ReservationStatus::Reserved === $this->find($key)?->status) {
            unset($this->reservations[$key->value]);
        }
    }

    #[Override]
    public function held(string $reward, ?ResourceReference $member = null): int
    {
        $held = 0;

        foreach ($this->reservations as $reservation) {
            if ($reservation->reward === $reward
                && \in_array($reservation->status, ReservationStatus::holding(), true)
                && (!$member instanceof ResourceReference || $reservation->member->equals($member))) {
                $held += $reservation->quantity;
            }
        }

        return $held;
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        $due = array_filter($this->reservations, static fn (Reservation $reservation): bool => $reservation->isDue($now));
        usort($due, static function (Reservation $a, Reservation $b): int {
            $byMoment = $a->expiresAt <=> $b->expiresAt;

            return 0 !== $byMoment ? $byMoment : strcmp($a->key->value, $b->key->value);
        });

        return \array_slice($due, 0, $limit);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return array_values(array_filter($this->reservations, static fn (Reservation $reservation): bool => $reservation->member->equals($member)));
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $before = \count($this->reservations);
        $this->reservations = array_filter($this->reservations, static fn (Reservation $reservation): bool => !$reservation->member->equals($member));

        return $before - \count($this->reservations);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->reservations = [];
    }
}
