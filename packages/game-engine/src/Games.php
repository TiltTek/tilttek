<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Contract\Game;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\Exception\UnknownTable;

/**
 * The games an application offers, by name.
 */
final readonly class Games
{
    /** @var array<string, Game> */
    private array $games;

    /**
     * @throws InvalidGame when two games share a name
     */
    public function __construct(Game ...$games)
    {
        $byName = [];

        foreach ($games as $game) {
            $name = $game->definition()->game;

            if (isset($byName[$name])) {
                throw InvalidGame::because(\sprintf('Two games are named "%s".', $name));
            }

            $byName[$name] = $game;
        }

        $this->games = $byName;
    }

    /**
     * @throws UnknownTable
     */
    public function get(string $name): Game
    {
        return $this->games[$name] ?? throw UnknownTable::game($name);
    }

    /**
     * @return list<string>
     */
    public function names(): array
    {
        return array_keys($this->games);
    }
}
