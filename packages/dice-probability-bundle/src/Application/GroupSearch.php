<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Application;

use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Explosion;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Limits;
use Tilttek\DiceProbabilityBundle\Application\Exception\GroupsTooCostly;

/**
 * The work of the groups an estimate looks for, `6d6g10`, weighed before any die is rolled: the probabilities of a set
 * that forms groups are always estimated, and each sample searches the most groups its dice form.
 *
 * That search is exhaustive up to `OPTIMAL` dice, and costs about `n × 2ⁿ` steps for `n` dice; past them, one greedy
 * pass costs about `n²`. A set is weighed by the most its dice may cost: from its written count, or from none for a
 * computed count or dice taken from other instructions, up to the most dice an expression rolls, with as many more as
 * the explosions of a roll may add. What `repeat()` repeats
 * counts once for each time, and every branch of a condition counts. An expression whose work over all the samples
 * passes `workLimit` is refused before the library rolls it.
 */
final readonly class GroupSearch
{
    /** The work done at most over all the samples, by default: sets of up to 6 dice on 5,000 samples. */
    public const int WORK_LIMIT = 2_000_000;

    /** The most dice whose groups the roller searches exhaustively; past them, it finds them greedily. */
    public const int OPTIMAL = 12;

    /**
     * @param Limits $limits    the limits the probabilities check an expression against: their dice bound a count
     *                          that is not written
     * @param int    $samples   the samples of an estimate, each a search of the groups
     * @param int    $workLimit the work done at most over all the samples
     */
    public function __construct(
        private Limits $limits = new Limits(),
        private int $samples = 5_000,
        private int $workLimit = self::WORK_LIMIT,
    ) {
    }

    /**
     * @throws GroupsTooCostly when the search of the groups would pass the work limit
     */
    public function check(Pool $pool, string $expression): void
    {
        $work = $this->times($this->work($pool->expressions), $this->samples);

        if ($work > $this->workLimit) {
            throw GroupsTooCostly::of($expression, $work, $this->workLimit);
        }
    }

    /**
     * The work of one sample: the search of the groups of every set the value holds, however deep.
     */
    public function work(mixed $value): int
    {
        if (\is_array($value)) {
            return $this->plus(...array_map($this->work(...), $value));
        }

        if (!\is_object($value) || !str_starts_with($value::class, 'Tilttek\\Dice\\Expression\\')) {
            return 0;
        }

        if ($value instanceof FunctionCall && MathFunction::Repeat === $value->function) {
            [$repeated, $times] = $value->arguments;

            return $this->plus($this->times($this->work($repeated), $times instanceof Constant ? max(0, $times->value) : $this->limits->repetitions), $this->work($times));
        }

        $own = property_exists($value, 'modifiers') && $value->modifiers instanceof Modifiers && null !== $value->modifiers->groups
            ? $this->search($value, $value->modifiers)
            : 0;

        return $this->plus($own, $this->work(get_object_vars($value)));
    }

    /**
     * The work of the search of the groups of one set: the most its dice may cost, from its written count, or from none
     * when it is not written, to the most dice an expression rolls, with as many more as its explosions may add.
     */
    private function search(object $set, Modifiers $modifiers): int
    {
        $written = match (true) {
            $set instanceof Dice => $set->count,
            $set instanceof ComputedDice && $set->count instanceof Constant => max(0, $set->count->value),
            default => null,
        };
        $most = $written ?? $this->limits->dice;

        if ($modifiers->explosion instanceof Explosion || $modifiers->explodeAllSame) {
            $most = $this->plus($most, $this->limits->explosions);
        }

        return $this->worst($written, $most);
    }

    /**
     * The most steps a search of between `$fewest` (none when the count is not written) and `$most` dice takes: the
     * exhaustive search of the most dice it may still do, or the greedy pass of the most dice.
     */
    private function worst(?int $fewest, int $most): int
    {
        if (null !== $fewest && $fewest > self::OPTIMAL) {
            return $this->steps($most);
        }

        return max($this->steps(min($most, self::OPTIMAL)), $this->steps($most));
    }

    /**
     * The steps of a search of the groups of `$dice` dice: exhaustive up to `OPTIMAL`, greedy beyond.
     */
    private function steps(int $dice): int
    {
        // `n × 2ⁿ` is `n` shifted left by `n` bits.
        return $dice > self::OPTIMAL ? $this->times($dice, $dice) : $dice << $dice;
    }

    /**
     * A sum of works, none below zero, that stops at the largest integer.
     */
    private function plus(int ...$terms): int
    {
        $sum = 0;

        foreach ($terms as $term) {
            $sum += min($term, \PHP_INT_MAX - $sum);
        }

        return $sum;
    }

    /**
     * A product of works, none below zero, that stops at the largest integer.
     */
    private function times(int $left, int $right): int
    {
        return 0 !== $left && $right > intdiv(\PHP_INT_MAX, $left) ? \PHP_INT_MAX : $left * $right;
    }
}
