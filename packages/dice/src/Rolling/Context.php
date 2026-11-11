<?php

declare(strict_types=1);

namespace Tilttek\Dice\Rolling;

use Random\Randomizer;
use RoundingMode;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Result\DiceResult;
use Tilttek\Dice\Result\NodeResult;

/**
 * What a roll keeps while it goes: its randomness, how its divisions round, the dice, explosions, rerolls and
 * repetitions counted against the limits across the pool, the set rolled last with its lowest and highest faces, for
 * "@", the results of the instructions already rolled, those whose dice went to a merge, and the subject of the branch
 * being rolled.
 *
 * @internal
 */
final class Context
{
    public ?DiceResult $previous = null;

    /**
     * The results of the instructions already rolled, in order.
     *
     * @var list<NodeResult>
     */
    public array $instructions = [];

    /**
     * By the number of an instruction from 1, the number of the instruction its dice were merged into.
     *
     * @var array<int, int>
     */
    public array $merged = [];

    public ?NodeResult $subject = null;

    public int $lowest = 0;

    public int $highest = 0;

    private int $dice = 0;

    private int $explosions = 0;

    private int $rerolls = 0;

    private int $repetitions = 0;

    public function __construct(
        public readonly Randomizer $randomizer,
        public RoundingMode $rounding,
        private readonly Limits $limits,
    ) {
    }

    public function roll(int $dice): void
    {
        $this->dice += $dice;
        $this->limits->checkDice($this->dice);
    }

    public function explode(): void
    {
        $this->limits->checkExplosions(++$this->explosions);
    }

    public function reroll(): void
    {
        $this->limits->checkRerolls(++$this->rerolls);
    }

    public function repeat(int $repetitions): void
    {
        $this->repetitions = Arithmetic::add($this->repetitions, $repetitions);
        $this->limits->checkRepetitions($this->repetitions);
    }

    public function text(string $text): void
    {
        $this->limits->checkText(mb_strlen($text));
    }

    public function remember(DiceResult $set, int $lowest, int $highest): void
    {
        $this->previous = $set;
        $this->lowest = $lowest;
        $this->highest = $highest;
    }

    /**
     * Expressions of a pool do not see each other's dice.
     */
    public function forget(): void
    {
        $this->previous = null;
    }
}
