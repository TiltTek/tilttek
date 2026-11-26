<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

use Tilttek\MiniGames\Exception\InvalidMiniGame;

/**
 * What a player's past daily games of one game add up to: the days played, the games won, the best score and how often
 * each score came. It counts no streak: a day missed takes nothing away.
 */
final readonly class DailyHistory
{
    /**
     * @param array<int, int> $distribution how many games ended on each score, by score, the best first
     */
    private function __construct(
        public int $played,
        public int $won,
        public ?int $best,
        public array $distribution,
    ) {
    }

    /**
     * @param iterable<DailyScore> $scores the player's scores of one game, one per day
     *
     * @throws InvalidMiniGame when the scores are of several games or players, or of one day twice
     */
    public static function of(iterable $scores, ScoreOrder $order): self
    {
        $days = [];
        $won = 0;
        $points = [];
        $distribution = [];
        $who = null;

        foreach ($scores as $score) {
            $who ??= $score->game."\n".$score->player;

            if ($who !== $score->game."\n".$score->player || isset($days[$score->day->value])) {
                throw InvalidMiniGame::because('A history holds the scores of one game and one player, one per day.');
            }

            $days[$score->day->value] = $score->day;
            $won += $score->won ? 1 : 0;

            if (null !== $score->score) {
                $points[] = $score->score;
                $distribution[$score->score] = ($distribution[$score->score] ?? 0) + 1;
            }
        }

        uksort($distribution, static fn (int $a, int $b): int => $order->isBetter($b, $a) <=> $order->isBetter($a, $b));

        return new self(\count($days), $won, $order->best($points), $distribution);
    }
}
