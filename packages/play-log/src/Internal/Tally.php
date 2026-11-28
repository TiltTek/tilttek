<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Internal;

use DateTimeImmutable;
use Tilttek\PlayLog\GameStatistics;
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\PlayerStatistics;
use Tilttek\PlayLog\Result;
use Tilttek\PlayLog\Seat;
use Tilttek\PlayLog\Statistics;

/**
 * The counts of one computation of statistics, made for it and dropped after it: the service that makes it keeps no
 * state. The plays come the latest first.
 *
 * @internal
 */
final class Tally
{
    private int $plays = 0;

    private int $wins = 0;

    private int $unfinished = 0;

    private int $minutes = 0;

    private int $timed = 0;

    /** @var array<string, array{plays: int, wins: int, minutes: int, last: ?DateTimeImmutable, score: ?int}> */
    private array $games = [];

    /** @var array<string, array{plays: int, wins: int}> */
    private array $players = [];

    /**
     * @param ?string $member the member whose plays are counted, or null for a group
     */
    public function __construct(private readonly ?string $member)
    {
    }

    public function add(Play $play): void
    {
        ++$this->plays;
        $game = $this->games[$play->game] ?? ['plays' => 0, 'wins' => 0, 'minutes' => 0, 'last' => null, 'score' => null];
        ++$game['plays'];

        if (Result::Unfinished === $play->result) {
            ++$this->unfinished;
        }

        if (null !== $play->minutes) {
            $this->minutes += $play->minutes;
            $game['minutes'] += $play->minutes;
            ++$this->timed;
        }

        // The latest come first: the first date of a game is its last.
        $game['last'] ??= $play->playedAt;

        foreach ($play->seats as $seat) {
            if ($seat->confirmed && null !== $seat->member) {
                $game = $this->seat($play, $seat, $seat->member, $game);
            }
        }

        $this->games[$play->game] = $game;
    }

    public function statistics(int $maxPlayers, bool $truncated): Statistics
    {
        $group = null === $this->member;

        return new Statistics(
            $this->plays,
            $group ? null : $this->wins,
            $this->unfinished,
            $this->minutes,
            $this->timed,
            $this->gameStatistics($group),
            $this->playerStatistics($maxPlayers),
            $this->hIndex(),
            $truncated,
        );
    }

    /**
     * @param array{plays: int, wins: int, minutes: int, last: ?DateTimeImmutable, score: ?int} $game
     *
     * @return array{plays: int, wins: int, minutes: int, last: ?DateTimeImmutable, score: ?int}
     */
    private function seat(Play $play, Seat $seat, string $member, array $game): array
    {
        $won = $play->won($seat);

        if (null === $this->member || $member === $this->member) {
            $game['score'] = $this->highest($game['score'], $seat->score);
        }

        if ($member === $this->member) {
            if ($won) {
                ++$this->wins;
                ++$game['wins'];
            }

            return $game;
        }

        $player = $this->players[$member] ?? ['plays' => 0, 'wins' => 0];
        $this->players[$member] = ['plays' => $player['plays'] + 1, 'wins' => $player['wins'] + ($won ? 1 : 0)];

        return $game;
    }

    private function highest(?int $highest, ?int $score): ?int
    {
        return max($highest ?? $score, $score ?? $highest);
    }

    /**
     * @return list<GameStatistics>
     */
    private function gameStatistics(bool $group): array
    {
        $statistics = [];

        foreach ($this->games as $game => $counts) {
            $statistics[] = new GameStatistics($game, $counts['plays'], $group ? null : $counts['wins'], $counts['minutes'], $counts['last'], $counts['score']);
        }

        usort($statistics, static fn (GameStatistics $a, GameStatistics $b): int => [$b->plays, $a->game] <=> [$a->plays, $b->game]);

        return $statistics;
    }

    /**
     * @return list<PlayerStatistics>
     */
    private function playerStatistics(int $max): array
    {
        $statistics = [];

        foreach ($this->players as $player => $counts) {
            $statistics[] = new PlayerStatistics($player, $counts['plays'], $counts['wins']);
        }

        usort($statistics, static fn (PlayerStatistics $a, PlayerStatistics $b): int => [$b->plays, $a->member] <=> [$a->plays, $b->member]);

        return \array_slice($statistics, 0, $max);
    }

    /**
     * The most games played at least as many times as their number.
     */
    private function hIndex(): int
    {
        $counts = array_column($this->games, 'plays');
        rsort($counts);
        $h = 0;

        foreach ($counts as $rank => $count) {
            if ($count > $rank) {
                $h = $rank + 1;
            }
        }

        return $h;
    }
}
