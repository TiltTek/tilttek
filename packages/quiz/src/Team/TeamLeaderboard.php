<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Team;

use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Leaderboard;
use Tilttek\Quiz\Standing;

/**
 * The standings of the teams of a live session, made from the standings of their players by the session's
 * `TeamScoring`: the highest score first; between equal scores, the team whose scoring answers took the fewer
 * milliseconds; teams equal on both share a rank, and the next rank skips the places they take (1, 1, 3).
 */
final readonly class TeamLeaderboard
{
    /**
     * @param list<TeamStanding> $standings in rank order
     */
    private function __construct(public array $standings)
    {
    }

    /**
     * @param list<string>       $teams   the teams
     * @param list<list<string>> $members the players of each team, in the order of the teams
     */
    public static function of(array $teams, array $members, Leaderboard $players, TeamScoring $scoring): self
    {
        if (\count($teams) !== \count($members)) {
            throw InvalidQuiz::because(\sprintf('The standings of %d teams are made from the players of %d.', \count($teams), \count($members)));
        }

        $byPlayer = [];

        foreach ($players->standings as $standing) {
            $byPlayer[$standing->player] = $standing;
        }

        $rows = [];

        foreach ($teams as $index => $team) {
            $score = 0;
            $right = 0;
            $time = 0;

            foreach ($members[$index] as $member) {
                $standing = $byPlayer[$member] ?? null;

                if ($standing instanceof Standing) {
                    $score += $standing->score;
                    $right += $standing->right;
                    $time += $standing->time;
                }
            }

            $count = \count($members[$index]);

            if (TeamScoring::Average === $scoring && $count > 0) {
                $score = self::mean($score, $count);
                $time = self::mean($time, $count);
            }

            $rows[] = ['team' => $team, 'score' => $score, 'right' => $right, 'time' => $time, 'players' => $count];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['score'], $a['time'], $a['team']] <=> [$a['score'], $b['time'], $b['team']]);
        $standings = [];
        $previous = null;

        foreach ($rows as $place => $row) {
            $rank = $previous instanceof TeamStanding && $previous->score === $row['score'] && $previous->time === $row['time'] ? $previous->rank : $place + 1;
            $previous = new TeamStanding($rank, $row['team'], $row['score'], $row['right'], $row['time'], $row['players']);
            $standings[] = $previous;
        }

        return new self($standings);
    }

    /**
     * The first `$count` standings, ties at the edge included.
     *
     * @return list<TeamStanding>
     */
    public function top(int $count): array
    {
        if ($count < 1) {
            throw InvalidQuiz::because(\sprintf('The top of the standings holds at least one place; %d asked.', $count));
        }

        return array_values(array_filter($this->standings, static fn (TeamStanding $standing): bool => $standing->rank <= $count));
    }

    public function standingOf(string $team): ?TeamStanding
    {
        foreach ($this->standings as $standing) {
            if ($standing->team === $team) {
                return $standing;
            }
        }

        return null;
    }

    /**
     * The mean, rounded to the nearest whole number, half up, in integers only.
     */
    private static function mean(int $sum, int $count): int
    {
        return intdiv(2 * $sum + $count, 2 * $count);
    }
}
