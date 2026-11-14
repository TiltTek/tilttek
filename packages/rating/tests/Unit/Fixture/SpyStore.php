<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit\Fixture;

use Override;
use Tilttek\Rating\Contract\RatingStore;
use Tilttek\Rating\Store\InMemoryRatingStore;

/**
 * A store of tests that notes the standings it is asked for, and that may claim a result is counted although its write
 * would take it, to tell the check of `Ratings` from the one of the store.
 */
final class SpyStore implements RatingStore
{
    /** @var list<array{int, int}> the limit and the games of each reading of the standings */
    public array $reads = [];

    public function __construct(private readonly InMemoryRatingStore $store, private readonly bool $recorded = false)
    {
    }

    #[Override]
    public function find(string $game, array $members): array
    {
        return $this->store->find($game, $members);
    }

    #[Override]
    public function hasRecorded(string $game, string $result): bool
    {
        return $this->recorded || $this->store->hasRecorded($game, $result);
    }

    #[Override]
    public function save(array $ratings, ?string $result = null, array $events = []): void
    {
        $this->store->save($ratings, $result, $events);
    }

    #[Override]
    public function standings(string $game, int $limit, int $minGames): array
    {
        $this->reads[] = [$limit, $minGames];

        return $this->store->standings($game, $limit, $minGames);
    }

    #[Override]
    public function ofGame(string $game): array
    {
        return $this->store->ofGame($game);
    }

    #[Override]
    public function replace(string $game, array $ratings, array $results): void
    {
        $this->store->replace($game, $ratings, $results);
    }

    #[Override]
    public function ofMember(string $member): array
    {
        return $this->store->ofMember($member);
    }

    #[Override]
    public function forget(string $member): void
    {
        $this->store->forget($member);
    }

    #[Override]
    public function undelivered(string $game, string $result): array
    {
        return $this->store->undelivered($game, $result);
    }

    #[Override]
    public function markDelivered(string $game, string $result, string ...$keys): void
    {
        $this->store->markDelivered($game, $result, ...$keys);
    }
}
