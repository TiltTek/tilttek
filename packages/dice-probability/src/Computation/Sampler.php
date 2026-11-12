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
 * samples are counted against a limit.
 *
 * @internal
 */
final readonly class Sampler
{
    public function __construct(
        private Roller $roller,
        private int $samples,
        private int $seed,
        private int $rollLimit,
    ) {
    }

    /**
     * How many rolls gave each total.
     *
     * @return array<int, Natural>
     *
     * @throws CannotCompute when the samples roll more dice than the limit
     */
    public function totals(Node $expression, RoundingMode $rounding): array
    {
        $pool = new Pool($expression);
        $randomizer = new Randomizer(new Xoshiro256StarStar($this->seed));
        $counts = [];
        $rolled = 0;

        for ($sample = 0; $sample < $this->samples; ++$sample) {
            $result = $this->roller->roll($pool, $randomizer, $rounding)->results[0]->result;
            $rolled = $this->count($rolled, $result);
            $counts[$result->value()] = ($counts[$result->value()] ?? 0) + 1;
        }

        return array_map(Natural::of(...), $counts);
    }

    /**
     * How many rolls showed the event on the dice that count.
     *
     * @throws CannotCompute when the samples roll more dice than the limit
     */
    public function hits(Dice $dice, Event $event): int
    {
        $pool = new Pool($dice);
        $randomizer = new Randomizer(new Xoshiro256StarStar($this->seed));
        $hits = 0;
        $rolled = 0;

        for ($sample = 0; $sample < $this->samples; ++$sample) {
            $result = $this->roller->roll($pool, $randomizer)->results[0]->result;
            \assert($result instanceof DiceResult);
            $rolled = $this->count($rolled, $result);

            if ($event->matches(Faces::of(array_map(static fn (RolledDie $die): int => $die->value, $result->kept())))) {
                ++$hits;
            }
        }

        return $hits;
    }

    /**
     * The dice rolled so far, with those of one more sample: every die of every set, rerolled or exploded.
     *
     * @throws CannotCompute when they go past the limit
     */
    private function count(int $rolled, NodeResult $result): int
    {
        foreach ($result->sets() as $set) {
            $rolled += \count($set->dice);
        }

        if ($rolled > $this->rollLimit) {
            throw CannotCompute::tooManyRolls($this->rollLimit, $this->samples);
        }

        return $rolled;
    }
}
