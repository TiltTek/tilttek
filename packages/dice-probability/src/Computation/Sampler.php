<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Computation;

use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use RoundingMode;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Result\DiceResult;
use Tilttek\Dice\Result\NodeResult;
use Tilttek\Dice\Result\RolledDie;
use Tilttek\Dice\Rolling\Roller;
use Tilttek\DiceProbability\Event\Event;
use Tilttek\DiceProbability\Event\Faces;
use Tilttek\DiceProbability\Exact\Natural;
use Tilttek\DiceProbability\Exception\CannotCompute;

/**
 * Rolls drawn from a fixed seed, for an estimate: the same seed gives the same estimate. The dice rolled over all the
 * samples are counted against a limit, and so are the steps of the search of the groups the sets form: `n × 2ⁿ` for the
 * `n` dice that count in a set searched exhaustively, up to 12 as the roller does, and `n²` for one greedy pass beyond.
 *
 * @internal
 */
final readonly class Sampler
{
    /** The most dice whose groups the roller searches exhaustively; past them, it finds them greedily. */
    private const int OPTIMAL = 12;

    public function __construct(
        private Roller $roller,
        private int $samples,
        private int $seed,
        private int $rollLimit,
        private int $groupWorkLimit,
    ) {
    }

    /**
     * How many rolls gave each total.
     *
     * @return array<int, Natural>
     *
     * @throws CannotCompute when the samples roll more dice, or search their groups longer, than the limits
     */
    public function totals(Node $expression, RoundingMode $rounding): array
    {
        $pool = new Pool($expression);
        $randomizer = new Randomizer(new Xoshiro256StarStar($this->seed));
        $counts = [];
        $spent = [0, 0];

        for ($sample = 0; $sample < $this->samples; ++$sample) {
            $result = $this->roller->roll($pool, $randomizer, $rounding)->results[0]->result;
            $spent = $this->count($spent, $result);
            $counts[$result->value()] = ($counts[$result->value()] ?? 0) + 1;
        }

        return array_map(Natural::of(...), $counts);
    }

    /**
     * How many rolls showed the event on the dice that count.
     *
     * @throws CannotCompute when the samples roll more dice, or search their groups longer, than the limits
     */
    public function hits(Dice $dice, Event $event): int
    {
        $pool = new Pool($dice);
        $randomizer = new Randomizer(new Xoshiro256StarStar($this->seed));
        $hits = 0;
        $spent = [0, 0];

        for ($sample = 0; $sample < $this->samples; ++$sample) {
            $result = $this->roller->roll($pool, $randomizer)->results[0]->result;
            \assert($result instanceof DiceResult);
            $spent = $this->count($spent, $result);

            if ($event->matches(Faces::of(array_map(static fn (RolledDie $die): int => $die->value, $result->kept())))) {
                ++$hits;
            }
        }

        return $hits;
    }

    /**
     * The dice rolled and the steps of the group search so far, with those of one more sample: every die of every set,
     * rerolled or exploded, and the search of the groups of every set that forms them.
     *
     * @param array{int, int} $spent
     *
     * @return array{int, int}
     *
     * @throws CannotCompute when they go past a limit
     */
    private function count(array $spent, NodeResult $result): array
    {
        [$rolled, $work] = $spent;

        foreach ($result->sets() as $set) {
            $rolled += \count($set->dice);
            $work += $this->groupWork($set);
        }

        if ($rolled > $this->rollLimit) {
            throw CannotCompute::tooManyRolls($this->rollLimit, $this->samples);
        }

        if ($work > $this->groupWorkLimit) {
            throw CannotCompute::tooMuchGroupWork($this->groupWorkLimit, $this->samples);
        }

        return [$rolled, $work];
    }

    /**
     * The steps of the search of the groups of one set: none when it forms no groups that reach a sum.
     */
    private function groupWork(DiceResult $set): int
    {
        if (null === $set->expression->modifiers->groups) {
            return 0;
        }

        $dice = \count(array_filter($set->dice, static fn (RolledDie $die): bool => $die->counts()));

        // `n × 2ⁿ` is `n` shifted left by `n` bits.
        return $dice > self::OPTIMAL ? $dice * $dice : $dice << $dice;
    }
}
