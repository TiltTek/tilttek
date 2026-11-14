<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit\Fixture;

use Override;
use Tilttek\Rating\Contract\RatingStore;
use Tilttek\Rating\MemberRating;
use Tilttek\Rating\Store\InMemoryRatingStore;

/**
 * A store of tests that keeps the list of ratings a replay hands over to replace the ones kept.
 */
final class ReplaceSpy implements RatingStore
{
    /** @var array<array-key, MemberRating> */
    public array $replaced = [];

    /** @var list<string> the results a replay hands over */
    public array $results = [];

    /** @var list<string> the games the ratings were read for */
    public array $found = [];

    public function __construct(private readonly InMemoryRatingStore $store)
    {
    }

    #[Override]
    public function find(string $game, array $members): array
    {
        $this->found[] = $game;

        return $this->store->find($game, $members);
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
        $this->replaced = $ratings;
        $this->results = $results;
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
