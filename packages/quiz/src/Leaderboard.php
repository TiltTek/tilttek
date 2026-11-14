<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

use Tilttek\Quiz\Exception\InvalidQuiz;

/**
 * The standings of a live session: the highest score first; between equal scores, the player whose scoring answers
 * took the fewer milliseconds first; players equal on both share a rank, and the next rank skips the places they take
 * (1, 1, 3). A wrong answer, or none, never counts in the time.
 */
final readonly class Leaderboard
{
    /**
     * @param list<Standing> $standings in rank order
     */
    private function __construct(public array $standings)
    {
    }

    /**
     * @param list<string>                      $players
     * @param list<array<string, AnswerResult>> $results the results of each question, by player
     */
    public static function of(array $players, array $results): self
    {
        $rows = [];

        foreach ($players as $player) {
            $score = 0;
            $right = 0;
            $time = 0;

            foreach ($results as $byPlayer) {
                $result = $byPlayer[$player] ?? null;

                if (null === $result) {
                    continue;
                }

                $score += $result->total();
                $right += $result->grade->isRight() ? 1 : 0;
                $time += $result->total() > 0 ? $result->elapsed : 0;
            }

            $rows[] = ['player' => $player, 'score' => $score, 'right' => $right, 'time' => $time];
        }

        usort($rows, static fn (array $a, array $b): int => [$b['score'], $a['time'], $a['player']] <=> [$a['score'], $b['time'], $b['player']]);
        $standings = [];
        $previous = null;

        foreach ($rows as $place => $row) {
            $rank = $previous instanceof Standing && $previous->score === $row['score'] && $previous->time === $row['time'] ? $previous->rank : $place + 1;
            $previous = new Standing($rank, $row['player'], $row['score'], $row['right'], $row['time']);
            $standings[] = $previous;
        }

        return new self($standings);
    }

    /**
     * The first `$count` standings, ties at the edge included: a podium of three shows four players tied for third.
     *
     * @return list<Standing>
     */
    public function top(int $count): array
    {
        if ($count < 1) {
            throw InvalidQuiz::because(\sprintf('The top of the standings holds at least one place; %d asked.', $count));
        }

        return array_values(array_filter($this->standings, static fn (Standing $standing): bool => $standing->rank <= $count));
    }

    public function standingOf(string $player): ?Standing
    {
        foreach ($this->standings as $standing) {
            if ($standing->player === $player) {
                return $standing;
            }
        }

        return null;
    }
}
