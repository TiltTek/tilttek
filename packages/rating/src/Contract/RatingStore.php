<?php

declare(strict_types=1);

namespace Tilttek\Rating\Contract;

use Tilttek\Rating\Exception\RatingConflict;
use Tilttek\Rating\Exception\ResultAlreadyRecorded;
use Tilttek\Rating\MemberRating;

/**
 * Where the members' ratings are kept, one per member and game, at a version, with the identifiers of the results each
 * game counts, so that a result is never counted twice. `Store\InMemoryRatingStore` keeps them in memory; an application
 * keeps them in its database.
 */
interface RatingStore
{
    /**
     * The ratings of these members for the game, by member; a member without one is left out.
     *
     * @param list<string> $members
     *
     * @return array<string, MemberRating>
     */
    public function find(string $game, array $members): array;

    /**
     * Whether the result of the game is counted already.
     */
    public function hasRecorded(string $game, string $result): bool;

    /**
     * Keeps the ratings at once, each one at the next version: a rating at version 0 is new, and the store has none for
     * its member and game; any other is kept only when the store holds it at that version. With a result, the result
     * is counted for the game of the ratings in the same write.
     *
     * @param list<MemberRating> $ratings
     *
     * @throws ResultAlreadyRecorded when the result is counted already; nothing is kept
     * @throws RatingConflict        when a rating changed or was added since it was read; nothing is kept
     */
    public function save(array $ratings, ?string $result = null): void;

    /**
     * The public ratings of the game with at least `$minGames` rated games, the highest score first, then by member.
     *
     * @return list<MemberRating>
     */
    public function standings(string $game, int $limit, int $minGames): array;

    /**
     * Every rating of the game, public or not.
     *
     * @return list<MemberRating>
     */
    public function ofGame(string $game): array;

    /**
     * Replaces every rating of the game, and the results it counts, with these, at once: what a replay computed.
     *
     * @param list<MemberRating> $ratings
     * @param list<string>       $results the identifiers of the results the ratings count
     */
    public function replace(string $game, array $ratings, array $results): void;

    /**
     * Every rating of the member, whatever the game.
     *
     * @return list<MemberRating>
     */
    public function ofMember(string $member): array;

    /**
     * Erases every rating of the member.
     */
    public function forget(string $member): void;
}
