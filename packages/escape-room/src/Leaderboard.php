<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom;

use Tilttek\EscapeRoom\Exception\InvalidRoom;

/**
 * The public leaderboard of a room: only the teams that agreed to show their result, by final time, then by fewer
 * hints, then by who escaped first. A team that did not agree is never on it; the leaderboard is private by default.
 */
final class Leaderboard
{
    public const int MAX_LIMIT = 1_000;

    private function __construct()
    {
    }

    /**
     * @param iterable<RoomResult> $results the results of the room; those of another room are left out
     * @param int                  $limit   how many places, 1 to 1 000
     *
     * @return list<Standing>
     *
     * @throws InvalidRoom when the limit is out of bounds
     */
    public static function rank(string $room, iterable $results, int $limit = 10): array
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT) {
            throw InvalidRoom::because(\sprintf('A leaderboard shows 1 to %d places.', self::MAX_LIMIT));
        }

        $keyed = [];

        foreach ($results as $result) {
            if ($result->public && $result->room === $room) {
                // The order read once per result, not once per comparison: the time to the microsecond, a number of fixed width.
                $keyed[] = [$result->finalSeconds, $result->hints, $result->escapedAt->format('Uu'), $result];
            }
        }

        usort($keyed, static fn (array $a, array $b): int => [$a[0], $a[1], $a[2]] <=> [$b[0], $b[1], $b[2]]);
        $shown = array_column($keyed, 3);
        $standings = [];
        $previous = null;

        foreach (\array_slice($shown, 0, $limit) as $index => $result) {
            $key = [$result->finalSeconds, $result->hints];
            $position = $index + 1;

            if ($key === $previous) {
                $position = $standings[$index - 1]->position;
            }

            $previous = $key;
            $standings[] = new Standing($position, $result);
        }

        return $standings;
    }
}
