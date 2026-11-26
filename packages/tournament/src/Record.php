<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

/**
 * What a participant did in a stage: their matches in round order, and what the pairings and the standings read of
 * them.
 */
final readonly class Record
{
    /**
     * @param list<Game> $games in round order
     */
    public function __construct(
        public string $participant,
        public array $games = [],
    ) {
    }

    public function points(): int
    {
        return array_sum(array_map(static fn (Game $game): int => $game->points, $this->games));
    }

    public function mission(): int
    {
        return array_sum(array_map(static fn (Game $game): int => $game->mission, $this->games));
    }

    /**
     * The matches won over the board: byes and forfeits are left out.
     */
    public function wins(): int
    {
        return \count(array_filter($this->games, static fn (Game $game): bool => $game->isPlayed() && $game->isWin()));
    }

    /**
     * The byes scored; rests are left out.
     */
    public function byes(): int
    {
        return \count(array_filter($this->games, static fn (Game $game): bool => null === $game->opponent && !$game->rest));
    }

    /**
     * The rounds the participant played an opponent over the board, by opponent: a forfeit is not a meeting, and the two
     * may be paired again.
     *
     * @return array<string, list<int>>
     */
    public function met(): array
    {
        $met = [];

        foreach ($this->games as $game) {
            if (null !== $game->opponent && $game->isPlayed()) {
                $met[$game->opponent][] = $game->round;
            }
        }

        return $met;
    }

    /**
     * Every opponent the participant was paired with, played or forfeited, once per match, in round order.
     *
     * @return list<string>
     */
    public function opponents(): array
    {
        $opponents = [];

        foreach ($this->games as $game) {
            if (null !== $game->opponent) {
                $opponents[] = $game->opponent;
            }
        }

        return $opponents;
    }

    /**
     * The sides of the matches played over the board, in round order: true for first.
     *
     * @return list<bool>
     */
    public function sides(): array
    {
        $sides = [];

        foreach ($this->games as $game) {
            if ($game->isPlayed()) {
                $sides[] = $game->first;
            }
        }

        return $sides;
    }

    /**
     * How many more times the participant played first than second, over the board.
     */
    public function sideBalance(): int
    {
        $balance = 0;

        foreach ($this->sides() as $first) {
            $balance += $first ? 1 : -1;
        }

        return $balance;
    }

    /**
     * The side the participant played the last time they met an opponent over the board: true for first.
     */
    public function lastSideAgainst(string $opponent): ?bool
    {
        $side = null;

        foreach ($this->games as $game) {
            if ($opponent === $game->opponent && $game->isPlayed()) {
                $side = $game->first;
            }
        }

        return $side;
    }
}
