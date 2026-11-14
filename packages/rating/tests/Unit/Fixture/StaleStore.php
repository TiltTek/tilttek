<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit\Fixture;

use Override;
use Tilttek\Rating\Contract\RatingStore;
use Tilttek\Rating\MemberRating;
use Tilttek\Rating\Store\InMemoryRatingStore;

/**
 * A store of tests that reads the ratings one version late, as a request that read them before another wrote them.
 */
final readonly class StaleStore implements RatingStore
{
    public function __construct(private InMemoryRatingStore $store)
    {
    }

    #[Override]
    public function find(string $game, array $members): array
    {
        return array_map(static fn (MemberRating $rating): MemberRating => $rating->withVersion($rating->version - 1), $this->store->find($game, $members));
    }

    #[Override]
    public function hasRecorded(string $game, string $result): bool
    {
        return $this->store->hasRecorded($game, $result);
    }

    #[Override]
    public function save(array $ratings, ?string $result = null, array $events = []): void
    {
        $this->store->save($ratings, $result, $events);
    }

    #[Override]
    public function standings(string $game, int $limit, int $minGames): array
    {
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
