<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application\Contract;

use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoom\RoomResult;

/**
 * Where the results of the teams that escaped are kept, by game, for the public leaderboard.
 */
interface ResultStore
{
    public function record(string $game, RoomResult $result): void;

    /**
     * Records whether the team agrees to show the result of a game; nothing when the game has no result.
     */
    public function consent(string $game, bool $public): void;

    /**
     * The results the teams agreed to show, by final time, then by fewer hints, then by who escaped first.
     *
     * @param int $limit how many, 1 to 1 000
     *
     * @return list<RoomResult>
     */
    public function shown(string $room, int $limit): array;

    /**
     * @return array<string, RoomResult> the results of a team, by game
     */
    public function ofTeam(ResourceReference $team): array;

    /**
     * Deletes the results of a team, and so withdraws them from the leaderboard.
     *
     * @return int the results deleted
     */
    public function forgetTeam(ResourceReference $team): int;
}
