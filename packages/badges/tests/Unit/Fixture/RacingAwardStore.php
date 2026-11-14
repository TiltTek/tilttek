<?php

declare(strict_types=1);

namespace Tilttek\Badges\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Award;
use Tilttek\Badges\Store\AwardStore;
use Tilttek\Badges\Store\InMemoryAwardStore;

/**
 * Awards in memory, where another request gets there first: before each award it is asked to keep, the store keeps the
 * next of its rivals, as a concurrent request would between the reading of the awards and the insertion.
 */
final class RacingAwardStore implements AwardStore
{
    /**
     * @var list<Award>
     */
    public array $rivals = [];

    public function __construct(
        public readonly InMemoryAwardStore $store = new InMemoryAwardStore(),
    ) {
    }

    #[Override]
    public function add(Award $award): bool
    {
        $rival = array_shift($this->rivals);

        if ($rival instanceof Award) {
            $this->store->add($rival);
        }

        return $this->store->add($award);
    }

    #[Override]
    public function markDelivered(Award $award): void
    {
        $this->store->markDelivered($award);
    }

    #[Override]
    public function undelivered(ResourceReference $member): array
    {
        return $this->store->undelivered($member);
    }

    #[Override]
    public function withdraw(Award $award): bool
    {
        return $this->store->withdraw($award);
    }

    #[Override]
    public function withdrawn(ResourceReference $member): array
    {
        return $this->store->withdrawn($member);
    }

    #[Override]
    public function remove(Award $award): void
    {
        $this->store->remove($award);
    }

    #[Override]
    public function of(ResourceReference $member, ?string $badge = null): array
    {
        return $this->store->of($member, $badge);
    }

    #[Override]
    public function forgetDerived(?DateTimeImmutable $since): array
    {
        return $this->store->forgetDerived($since);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return $this->store->eraseMember($member);
    }
}
