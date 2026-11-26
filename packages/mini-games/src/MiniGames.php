<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

use Tilttek\GameEngine\Games;
use Tilttek\MiniGames\Contract\MiniGame;
use Tilttek\MiniGames\Exception\InvalidMiniGame;

/**
 * The mini-games of an application, by the name of their definition; `games()` gives them to the engine's `Tables`.
 */
final readonly class MiniGames
{
    /** @var array<string, MiniGame> */
    private array $games;

    /**
     * @throws InvalidMiniGame when two games share a name
     */
    public function __construct(MiniGame ...$games)
    {
        $byName = [];

        foreach ($games as $game) {
            $name = $game->definition()->game;

            if (isset($byName[$name])) {
                throw InvalidMiniGame::because(\sprintf('Two mini-games are named "%s".', $name));
            }

            $byName[$name] = $game;
        }

        $this->games = $byName;
    }

    /**
     * @throws InvalidMiniGame when no mini-game has that name
     */
    public function get(string $name): MiniGame
    {
        return $this->games[$name] ?? throw InvalidMiniGame::because(\sprintf('No mini-game is named "%s".', $name));
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->games);
    }

    /**
     * The same games for the engine's `Tables`.
     */
    public function games(): Games
    {
        return new Games(...array_values($this->games));
    }
}
