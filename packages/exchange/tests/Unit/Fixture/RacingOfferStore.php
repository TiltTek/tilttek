<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Trade\Offer;
use Tilttek\Exchange\Trade\OfferStatus;
use Tilttek\Exchange\Trade\OfferStore;

/**
 * A store where another process always answers first: the offer is read open, and every closing comes too late.
 */
final readonly class RacingOfferStore implements OfferStore
{
    public function __construct(
        private OfferStore $inner,
        private Offer $offer,
    ) {
    }

    #[Override]
    public function open(Offer $offer): bool
    {
        return $this->inner->open($offer);
    }

    #[Override]
    public function find(ExchangeKey $key): ?Offer
    {
        return $key->value === $this->offer->key->value ? $this->offer : $this->inner->find($key);
    }

    #[Override]
    public function close(ExchangeKey $key, OfferStatus $status, DateTimeImmutable $at): bool
    {
        return false;
    }

    #[Override]
    public function holder(ResourceReference $item): ?ExchangeKey
    {
        return null;
    }

    #[Override]
    public function made(ResourceReference $proposer, ?DateTimeImmutable $since, ?DateTimeImmutable $until): int
    {
        return 0;
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        return [];
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
