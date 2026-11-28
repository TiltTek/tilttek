<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Odds;

use Random\Randomizer;
use Tilttek\TavernGames\Contract\Simulation;
use Tilttek\TavernGames\Game\ShipCaptainCrew;

/**
 * The turn of a seat at Ship, Captain and Crew, played to its end against the best cargo before it: the seat rolls
 * while rolls remain and it does not carry more than that cargo, and stops as soon as it does. A cargo above wins,
 * the same cargo ties, less loses; with no cargo before it, carrying none ties.
 */
final readonly class CargoChase implements Simulation
{
    /**
     * @param int       $held   the ship, captain and crew set aside, 0 to 3
     * @param list<int> $dice   the dice rolled last, without those set aside
     * @param int       $rolls  the rolls left, 0 to 3
     * @param int       $target the best cargo of the seats before it, 0 when none carries any
     */
    public function __construct(
        public int $held,
        public array $dice,
        public int $rolls,
        public int $target,
    ) {
    }

    public function trial(Randomizer $random): Trial
    {
        $held = $this->held;
        $dice = $this->dice;

        for ($left = $this->rolls; $left > 0 && $this->cargo($held, $dice) <= $this->target; --$left) {
            $roll = [];

            for ($die = 5 - $held; $die > 0; --$die) {
                $roll[] = $random->getInt(1, 6);
            }

            [$held, $dice] = ShipCaptainCrew::setAside($held, $roll);
        }

        return match ($this->cargo($held, $dice) <=> $this->target) {
            1 => Trial::Win,
            0 => Trial::Tie,
            default => Trial::Loss,
        };
    }

    /**
     * @param list<int> $dice
     */
    private function cargo(int $held, array $dice): int
    {
        return 3 === $held ? array_sum($dice) : 0;
    }
}
