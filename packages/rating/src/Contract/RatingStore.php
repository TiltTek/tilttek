<?php

declare(strict_types=1);

namespace Tilttek\Rating\Contract;

use Tilttek\Rating\Event\PeakReached;
use Tilttek\Rating\Event\RatingChanged;
use Tilttek\Rating\Exception\RatingConflict;
use Tilttek\Rating\Exception\ResultAlreadyRecorded;
use Tilttek\Rating\MemberRating;

/**
 * Where the members' ratings are kept, one per member and game, at a version, with the identifiers of the results each
 * game counts, so that a result is never counted twice. `Store\InMemoryRatingStore` keeps them in memory; an application
 * keeps them in its database.
 *
 * The events of a result are kept with it, in the same write, until `Ratings` marks each one delivered: a listener
 * that failed is made up later, and the result is never rated again for it.
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
     * is counted for the game of the ratings in the same write, with the events of the result, kept until they are
     * delivered.
     *
     * @param list<MemberRating>              $ratings
     * @param list<RatingChanged|PeakReached> $events  in the order they are dispatched
     *
     * @throws ResultAlreadyRecorded when the result is counted already; nothing is kept
     * @throws RatingConflict        when a rating changed or was added since it was read; nothing is kept
     */
    public function save(array $ratings, ?string $result = null, array $events = []): void;

    /**
     * The events of the result not delivered yet, in the order they were kept; none for a result unknown.
     *
     * @return list<RatingChanged|PeakReached>
     */
    public function undelivered(string $game, string $result): array;

    /**
     * Forgets the events of the result with these keys, dispatched without an exception; a key unknown is passed over.
     */
    public function markDelivered(string $game, string $result, string ...$keys): void;

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
     * Replaces every rating of the game, and the results it counts, with these, at once: what a replay computed. The
     * events of the game's results not delivered yet are cancelled in the same write: computed before the replay, they
     * may tell a change or a peak it no longer gives, and a replay tells nothing new.
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
     * Erases every rating of the member, and the events about them not delivered yet.
     */
    public function forget(string $member): void;
}
