<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * A statistic a game reports when it ends — the rounds played, each seat's points —, declared by its definition: a
 * whole number for the table, or one for each seat.
 */
final readonly class Statistic
{
    /**
     * @throws InvalidGame
     */
    private function __construct(
        public string $name,
        public StatisticScope $scope,
    ) {
        Name::check($name, 'a statistic');
    }

    /**
     * @throws InvalidGame
     */
    public static function table(string $name): self
    {
        return new self($name, StatisticScope::Table);
    }

    /**
     * @throws InvalidGame
     */
    public static function seat(string $name): self
    {
        return new self($name, StatisticScope::Seat);
    }
}
