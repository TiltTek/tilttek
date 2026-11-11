<?php

declare(strict_types=1);

namespace Tilttek\Exchange\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Trade\InMemoryOfferStore;
use Tilttek\Exchange\Trade\Offer;
use Tilttek\Exchange\Trade\OfferStatus;
use Tilttek\Exchange\Trade\OfferStore;

/**
 * A store where another process opens its offer under the same key just before this one.
 */
final readonly class LateOfferStore implements OfferStore
{
    private InMemoryOfferStore $inner;

    public function __construct(
        private Offer $competitor,
    ) {
        $this->inner = new InMemoryOfferStore();
    }

    #[Override]
    public function open(Offer $offer): bool
    {
        $this->inner->open($this->competitor);

        return $this->inner->open($offer);
    }

    #[Override]
    public function find(ExchangeKey $key): ?Offer
    {
        return $this->inner->find($key);
    }

    #[Override]
    public function close(ExchangeKey $key, OfferStatus $status, DateTimeImmutable $at): bool
    {
        return $this->inner->close($key, $status, $at);
    }

    #[Override]
    public function holder(ResourceReference $item): ?ExchangeKey
    {
        return $this->inner->holder($item);
    }

    #[Override]
    public function made(ResourceReference $proposer, ?DateTimeImmutable $since, ?DateTimeImmutable $until): int
    {
        return $this->inner->made($proposer, $since, $until);
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
