<?php

declare(strict_types=1);

namespace Tilttek\Rating\Store;

use Override;
use Tilttek\Rating\Contract\RatingStore;
use Tilttek\Rating\Exception\RatingConflict;
use Tilttek\Rating\Exception\ResultAlreadyRecorded;
use Tilttek\Rating\MemberRating;
use Tilttek\Rating\Visibility;

/**
 * The ratings in memory, for tests and scripts: they last as long as the object.
 */
final class InMemoryRatingStore implements RatingStore
{
    /** @var array<string, array<string, MemberRating>> by game, then member */
    private array $ratings = [];

    /** @var array<string, array<string, true>> the results counted, by game */
    private array $results = [];

    #[Override]
    public function hasRecorded(string $game, string $result): bool
    {
        return isset($this->results[$game][$result]);
    }

    #[Override]
    public function find(string $game, array $members): array
    {
        return array_intersect_key($this->ratings[$game] ?? [], array_flip($members));
    }

    #[Override]
    public function save(array $ratings, ?string $result = null): void
    {
        $game = $ratings[0]->game ?? null;

        if (null !== $result && null !== $game && $this->hasRecorded($game, $result)) {
            throw ResultAlreadyRecorded::of($game, $result);
        }

        foreach ($ratings as $rating) {
            if ($rating->version !== ($this->ratings[$rating->game][$rating->member]->version ?? 0)) {
                throw RatingConflict::because(\sprintf('The rating of the member %s for the game %s changed since it was read.', $rating->member, $rating->game));
            }
        }

        foreach ($ratings as $rating) {
            $this->ratings[$rating->game][$rating->member] = $rating->withVersion($rating->version + 1);
        }

        if (null !== $result && null !== $game) {
            $this->results[$game][$result] = true;
        }
    }

    #[Override]
    public function standings(string $game, int $limit, int $minGames): array
    {
        $least = max(1, $minGames);
        $public = array_filter($this->ratings[$game] ?? [], static fn (MemberRating $rating): bool => Visibility::Public === $rating->visibility && $rating->games >= $least);
        usort($public, static fn (MemberRating $a, MemberRating $b): int => [$b->score, $a->member] <=> [$a->score, $b->member]);

        return \array_slice($public, 0, $limit);
    }

    #[Override]
    public function ofGame(string $game): array
    {
        return array_values($this->ratings[$game] ?? []);
    }

    #[Override]
    public function replace(string $game, array $ratings, array $results): void
    {
        $before = $this->ratings[$game] ?? [];
        $this->ratings[$game] = [];
        $this->results[$game] = array_fill_keys($results, true);

        foreach ($ratings as $rating) {
            $this->ratings[$game][$rating->member] = $rating->withVersion(($before[$rating->member]->version ?? 0) + 1);
        }
    }

    #[Override]
    public function ofMember(string $member): array
    {
        $found = [];

        foreach ($this->ratings as $ratings) {
            if (isset($ratings[$member])) {
                $found[] = $ratings[$member];
            }
        }

        return $found;
    }

    #[Override]
    public function forget(string $member): void
    {
        foreach (array_keys($this->ratings) as $game) {
            unset($this->ratings[$game][$member]);
        }
    }
}
