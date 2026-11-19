<?php

declare(strict_types=1);

namespace Tilttek\DiceJail\Tests\Benchmark;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\DiceJail\Statistics\ExpectedFaces;
use Tilttek\DiceJail\Statistics\Explanation;
use Tilttek\DiceJail\Statistics\GoodnessOfFit;
use Tilttek\DiceJail\Statistics\Tally;

/**
 * What judging a die costs: 10 000 rolls of a d20 counted, tested and explained — the page a player opens before
 * sending a die to jail —, and the test of a d100 on its own, the most degrees of freedom a table uses.
 */
final class GoodnessOfFitBench
{
    /** @var list<int> */
    private readonly array $rolls;

    private readonly Tally $d100;

    private readonly GoodnessOfFit $test;

    /** What the last run gave, kept so that the work is not thrown away. */
    public string $explanation = '';

    public function __construct()
    {
        $random = new Randomizer(new Xoshiro256StarStar(20));
        $rolls = [];
        $d100 = [];

        for ($i = 0; $i < 10_000; ++$i) {
            $rolls[] = $random->getInt(1, 20);
            $d100[] = $random->getInt(1, 100);
        }

        $this->rolls = $rolls;
        $this->d100 = Tally::of($d100);
        $this->test = new GoodnessOfFit();
    }

    public function benchJudgeAD20(): void
    {
        $this->explanation = new Explanation()->explain($this->test->test(Tally::of($this->rolls), ExpectedFaces::uniform(20)));
    }

    public function benchTestAD100(): void
    {
        $this->test->test($this->d100, ExpectedFaces::uniform(100));
    }
}
