<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Benchmark;

use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\MiniGames\DailyGames;
use Tilttek\MiniGames\DailySeed;
use Tilttek\MiniGames\Day;
use Tilttek\MiniGames\Game\BullsAndCows;
use Tilttek\MiniGames\MiniGames;

/**
 * What the requests of a daily game do: open the table of the day with its seed, play every guess, show the view and
 * the shared result. A game of Bulls and Cows that runs out of its ten guesses is the longest of the example games.
 */
final class DailyGameBench
{
    private int $player = 0;

    public function benchAWholeGameOfTenGuesses(): void
    {
        $games = new MiniGames(new BullsAndCows());
        $daily = new DailyGames(new Tables($games->games(), new InMemoryLogStore()), $games, new DailySeed(str_repeat('s', 32)));
        $day = Day::fromString('2026-11-24');
        $player = 'u-'.++$this->player;
        $daily->start('bulls-and-cows', $day, $player);

        foreach (['0123', '4567', '8901', '2345', '6789', '1032', '5476', '9810', '3254', '7698'] as $guess) {
            try {
                $daily->play('bulls-and-cows', $day, $player, BullsAndCows::GUESS, ['guess' => $guess]);
            } catch (IllegalMove) {
                break;
            }

            $daily->view('bulls-and-cows', $day, $player);
        }

        $daily->share('bulls-and-cows', $day, $player)->toText('Bulls and Cows 1');
    }
}
