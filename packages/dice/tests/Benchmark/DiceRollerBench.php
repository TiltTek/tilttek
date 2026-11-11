<?php

declare(strict_types=1);

namespace Tilttek\Dice\Tests\Benchmark;

use Generator;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Limits;

/**
 * Every roll of a table is parsed from what a player typed, then rolled: a plain roll, an expression of exploding and
 * kept dice, a pool of several expressions, an expression of modifiers, instructions that speak of each other, the
 * most groups of twelve dice, and the largest roll the limits allow.
 *
 * @BeforeMethods("setUp")
 */
final class DiceRollerBench
{
    private readonly DiceRoller $dice;

    private Pool $pool;

    private string $input = '';

    private readonly Randomizer $randomizer;

    public function __construct()
    {
        $this->dice = new DiceRoller(new Limits(dice: 1000, explosions: 1000));
        $this->pool = $this->dice->parse('1d6');
        $this->randomizer = new Randomizer(new Xoshiro256StarStar(42));
    }

    /**
     * @param array{input: string} $params
     */
    public function setUp(array $params): void
    {
        $this->input = $params['input'];
        $this->pool = $this->dice->parse($this->input);
    }

    /**
     * @ParamProviders("inputs")
     *
     * @Revs(100)
     *
     * @Iterations(3)
     */
    public function benchParse(): void
    {
        $this->dice->parse($this->input);
    }

    /**
     * @ParamProviders("inputs")
     *
     * @Revs(100)
     *
     * @Iterations(3)
     */
    public function benchRoll(): void
    {
        $this->dice->roll($this->pool, $this->randomizer);
    }

    /**
     * @return Generator<string, array{input: string}>
     */
    public function inputs(): Generator
    {
        yield 'plain' => ['input' => '1d20+5'];
        yield 'kept and exploding' => ['input' => '4d6kh3 + 2d10! - 1'];
        yield 'a pool' => ['input' => '10d6!kh5, 4dF, 6d10>=7, 2d20kh1+3'];
        yield 'modifiers' => ['input' => '6d10r1!!min2>=7f1-@c[=10], 10d10o2[>=5], (1d4)d6sd+ceil(1d6/3)'];
        yield 'instructions' => ['input' => '8d10; @1c[>=6]; @1c[=1]; {2d6, 1d12}kh1; 3Lu[a,b[2],c,d]; repeat(4d6kh3, 6); 1d20i[>=15]{"hit $2"}{"miss"}; "%3"'];
        yield 'the most groups of twelve dice' => ['input' => '12d10g10'];
        yield 'a thousand dice' => ['input' => '1000d6'];
    }
}
