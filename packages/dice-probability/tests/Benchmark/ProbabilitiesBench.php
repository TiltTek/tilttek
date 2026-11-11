<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Tests\Benchmark;

use Generator;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Expression\Pool;
use Tilttek\DiceProbability\Event\AnyOf;
use Tilttek\DiceProbability\Event\Count;
use Tilttek\DiceProbability\Event\OfAKind;
use Tilttek\DiceProbability\Probabilities;

/**
 * The exact distributions a page of statistics asks for: a sum of plain dice, kept dice, successes counted, exploding
 * dice down to the depth of the settings, two expressions compared, and the chance of an event on a handful of dice.
 *
 * @BeforeMethods("setUp")
 */
final class ProbabilitiesBench
{
    private readonly DiceRoller $dice;

    private readonly Probabilities $probabilities;

    private readonly Pool $twoD10;

    private Pool $pool;

    public function __construct()
    {
        $this->dice = new DiceRoller();
        $this->probabilities = new Probabilities();
        $this->twoD10 = $this->dice->parse('2d10');
        $this->pool = $this->dice->parse('1d6');
    }

    /**
     * @param array{input: string} $params
     */
    public function setUp(array $params): void
    {
        $this->pool = $this->dice->parse($params['input']);
    }

    /**
     * @ParamProviders("expressions")
     *
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchDistribution(): void
    {
        $this->probabilities->distribution($this->pool)->mean();
    }

    /**
     * @ParamProviders("hands")
     *
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchChance(): void
    {
        $this->probabilities->chance(new AnyOf(Count::atLeastOne(1), OfAKind::double()), $this->pool);
    }

    /**
     * @ParamProviders("rivals")
     *
     * @Revs(20)
     *
     * @Iterations(3)
     */
    public function benchCompare(): void
    {
        $this->probabilities->compare($this->pool, $this->twoD10);
    }

    /**
     * @return Generator<string, array{input: string}>
     */
    public function expressions(): Generator
    {
        yield 'a sum' => ['input' => '3d6'];
        yield 'many dice' => ['input' => '20d6'];
        yield 'kept dice' => ['input' => '4d6kh3'];
        yield 'advantage' => ['input' => '2d20kh1+5'];
        yield 'successes' => ['input' => '8d10>=7'];
        yield 'exploding' => ['input' => '3d6!'];
    }

    /**
     * @return Generator<string, array{input: string}>
     */
    public function hands(): Generator
    {
        yield 'two dice' => ['input' => '2d6'];
        yield 'four dice' => ['input' => '4d6'];
        yield 'six dice' => ['input' => '6d6'];
    }

    /**
     * @return Generator<string, array{input: string}>
     */
    public function rivals(): Generator
    {
        yield 'against 2d10' => ['input' => '3d6'];
    }
}
