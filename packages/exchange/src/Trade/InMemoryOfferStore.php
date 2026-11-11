<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Trade;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Erasure;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Refusal;

/**
 * The offers in memory, for tests and for applications that keep nothing between requests.
 */
final class InMemoryOfferStore implements OfferStore
{
    /**
     * @var array<string, Offer>
     */
    private array $offers = [];

    #[Override]
    public function open(Offer $offer): bool
    {
        if (isset($this->offers[$offer->key->value])) {
            return false;
        }

        foreach ($offer->offered as $item) {
            $holder = $this->holder($item);

            if ($holder instanceof ExchangeKey) {
                throw ExchangeRefused::because(Refusal::AlreadyHeld, \sprintf('"%s" is held by the offer "%s".', $item->toString(), $holder->value));
            }
        }

        $this->offers[$offer->key->value] = $offer;

        return true;
    }

    #[Override]
    public function find(ExchangeKey $key): ?Offer
    {
        return $this->offers[$key->value] ?? null;
    }

    #[Override]
    public function close(ExchangeKey $key, OfferStatus $status, DateTimeImmutable $at): bool
    {
        $offer = $this->find($key);

        if (OfferStatus::Open !== $offer?->status) {
            return false;
        }

        $this->offers[$key->value] = $offer->closed($status, $at);

        return true;
    }

    #[Override]
    public function holder(ResourceReference $item): ?ExchangeKey
    {
        foreach ($this->offers as $offer) {
            if (OfferStatus::Open === $offer->status && array_any($offer->offered, static fn (ResourceReference $held): bool => $held->equals($item))) {
                return $offer->key;
            }
        }

        return null;
    }

    #[Override]
    public function made(ResourceReference $proposer, ?DateTimeImmutable $since, ?DateTimeImmutable $until): int
    {
        return \count(array_filter($this->offers, static fn (Offer $offer): bool => $offer->proposer->equals($proposer)
            && (!$since instanceof DateTimeImmutable || $offer->madeAt >= $since)
            && (!$until instanceof DateTimeImmutable || $offer->madeAt < $until)));
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        $due = array_filter($this->offers, static fn (Offer $offer): bool => $offer->isDue($now));
        usort($due, static function (Offer $a, Offer $b): int {
            $byMoment = $a->expiresAt <=> $b->expiresAt;

            return 0 !== $byMoment ? $byMoment : strcmp($a->key->value, $b->key->value);
        });

        return \array_slice($due, 0, $limit);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return array_values(array_filter($this->offers, static fn (Offer $offer): bool => $offer->proposer->equals($member) || $offer->recipient->equals($member)));
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $changed = 0;

        foreach ($this->ofMember($member) as $offer) {
            $this->offers[$offer->key->value] = $offer->without($member, Erasure::erasedMember());
            ++$changed;
        }

        return $changed;
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->offers = [];
    }
}
