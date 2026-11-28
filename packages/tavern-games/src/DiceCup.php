<?php

declare(strict_types=1);

namespace Tilttek\TavernGames;

use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Result\DiceResult;
use Tilttek\Dice\Result\RolledDie;
use Tilttek\GameEngine\Randomness;
use Tilttek\TavernGames\Exception\InvalidTavernGame;

/**
 * Six-sided dice rolled by `tilttek/dice` with the chance of a move, so that replaying the table rolls the same dice.
 */
final readonly class DiceCup
{
    /** The most dice of one roll. */
    public const int MAX_DICE = 10;

    public function __construct(
        private DiceRoller $roller = new DiceRoller(),
    ) {
    }

    /**
     * The faces of `$count` six-sided dice, in the order they fell.
     *
     * @return list<int>
     *
     * @throws InvalidTavernGame when the count is not 1 to `MAX_DICE`
     */
    public function roll(int $count, Randomness $random): array
    {
        if ($count < 1 || $count > self::MAX_DICE) {
            throw InvalidTavernGame::because(\sprintf('A roll throws 1 to %d dice.', self::MAX_DICE));
        }

        $result = $this->roller->roll($this->roller->parse($count.'d6'), $random->randomizer())->results[0]->result;

        if (!$result instanceof DiceResult) {
            throw InvalidTavernGame::because('A roll of six-sided dice gives a set of dice.');
        }

        return array_map(static fn (RolledDie $die): int => $die->value, $result->dice);
    }
}
