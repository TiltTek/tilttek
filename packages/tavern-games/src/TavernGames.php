<?php

declare(strict_types=1);

namespace Tilttek\TavernGames;

use Tilttek\GameEngine\Games;
use Tilttek\TavernGames\Contract\TavernGame;
use Tilttek\TavernGames\Exception\InvalidTavernGame;

/**
 * The tavern games of an application, by the name of their definition; `games()` gives them to the engine's `Tables`.
 */
final readonly class TavernGames
{
    /** @var array<string, TavernGame> */
    private array $games;

    /**
     * @throws InvalidTavernGame when two games share a name
     */
    public function __construct(TavernGame ...$games)
    {
        $byName = [];

        foreach ($games as $game) {
            $name = $game->definition()->game;

            if (isset($byName[$name])) {
                throw InvalidTavernGame::because(\sprintf('Two tavern games are named "%s".', $name));
            }

            $byName[$name] = $game;
        }

        $this->games = $byName;
    }

    /**
     * @throws InvalidTavernGame when no tavern game has that name
     */
    public function get(string $name): TavernGame
    {
        return $this->games[$name] ?? throw InvalidTavernGame::because(\sprintf('No tavern game is named "%s".', $name));
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
