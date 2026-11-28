<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Contract;

use Tilttek\PlayLog\Exception\InvalidPlay;
use Tilttek\PlayLog\Exception\PlayConflict;
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\PlayQuery;

/**
 * Keeps the plays, each at a version.
 */
interface PlayStore
{
    /**
     * The play, or null when none has the identifier.
     */
    public function play(string $id): ?Play;

    /**
     * Keeps the play at the next version, and returns it. A play at version 0 is new; any other is kept only when the
     * store holds it at that version.
     *
     * @throws PlayConflict when the play is new and the store has one of its identifier, or the store holds it at
     *                      another version; nothing is kept
     */
    public function save(Play $play): Play;

    /**
     * Deletes the play when the store holds it at that version.
     *
     * @throws PlayConflict when the store holds it at another version, or not at all; nothing is deleted
     */
    public function delete(Play $play): void;

    /**
     * The plays the query matches, the latest first, those whose date is unknown last, then by identifier; at most the
     * limit.
     *
     * @param int $limit 1 or more
     *
     * @return iterable<Play>
     *
     * @throws InvalidPlay when the limit is below 1
     */
    public function plays(PlayQuery $query, int $limit): iterable;
}
