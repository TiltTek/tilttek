<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability;

use RoundingMode;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Explosion;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Occurrences;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\Repetition;
use Tilttek\Dice\Expression\Selection;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Rolling\Roller;
use Tilttek\DiceProbability\Computation\Budget;
use Tilttek\DiceProbability\Computation\Calculator;
use Tilttek\DiceProbability\Computation\Multisets;
use Tilttek\DiceProbability\Computation\Sampler;
use Tilttek\DiceProbability\Computation\TooLarge;
use Tilttek\DiceProbability\Computation\Unsupported;
use Tilttek\DiceProbability\Event\Event;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;
use Tilttek\DiceProbability\Exception\CannotCompute;

/**
 * The probabilities of dice expressions parsed by `tilttek/dice`: the exact distribution of an expression, two
 * expressions against each other, and events on the faces of a set of dice. What the exact computation cannot follow,
 * or would take too long to, is estimated from seeded rolls and marked as estimated, with its margin.
 */
final readonly class Probabilities
{
    private Calculator $calculator;

    private Sampler $sampler;

    /**
     * @param CustomDice $customDice the custom dice the expressions may name, those of the `DiceRoller` that parsed them
     * @param Limits     $limits     the limits the expressions are checked against, as for a roll
     */
    public function __construct(
        CustomDice $customDice = new CustomDice(),
        private Limits $limits = new Limits(),
        private Settings $settings = new Settings(),
    ) {
        $this->calculator = new Calculator($customDice, $limits, $settings->explosionDepth);
        $this->sampler = new Sampler(new Roller($limits, $customDice), $settings->samples, $settings->seed);
    }

    /**
     * The distribution of one expression: a node, or a pool that holds one.
     *
     * @param RoundingMode $rounding how a division rounds, unless `floor()`, `ceil()` or `round()` say otherwise
     *
     * @throws CannotCompute when the pool holds several expressions, a die's faces carry no value, or an outcome
     *                       cannot be rolled
     * @throws LimitExceeded when the expression goes past a limit
     */
    public function distribution(Node|Pool $expression, RoundingMode $rounding = RoundingMode::NegativeInfinity): Distribution
    {
        $node = $this->single($expression);

        try {
            try {
                $weights = $this->calculator->evaluate($node, $rounding, new Budget($this->settings->workLimit));

                return new Distribution($weights->weights, $weights->remaining);
            } catch (TooLarge) {
                return new Distribution($this->sampler->totals($node, $rounding), estimate: $this->estimate(EstimateReason::TooLarge));
            } catch (Unsupported) {
                return new Distribution($this->sampler->totals($node, $rounding), estimate: $this->estimate(EstimateReason::Unsupported));
            }
        } catch (CannotRoll $cannotRoll) {
            throw CannotCompute::cannotRoll($cannotRoll);
        }
    }

    /**
     * Two expressions rolled against each other.
     *
     * @throws CannotCompute
     * @throws LimitExceeded
     */
    public function compare(Node|Pool $first, Node|Pool $second, RoundingMode $rounding = RoundingMode::NegativeInfinity): Versus
    {
        return new Versus($this->distribution($first, $rounding), $this->distribution($second, $rounding));
    }

    /**
     * The probability of an event on the faces of a set of dice — `4d6`, or `4d6r1` whose rerolled faces are those
     * that count —, exact over their sorted multisets, or estimated when the dice take other modifiers or are too many.
     *
     * @throws CannotCompute when the expression is not one set of dice, or its faces carry no value
     * @throws LimitExceeded when the dice go past a limit
     */
    public function chance(Event $event, Node|Pool $dice): Chance
    {
        $node = $this->single($dice);

        if (!$node instanceof Dice) {
            throw CannotCompute::notASet();
        }

        try {
            try {
                if (!$this->judgedByFaces($node->modifiers)) {
                    throw new Unsupported('Only rerolls leave the faces of a set as they fell.');
                }

                $budget = new Budget($this->settings->workLimit);
                [$faces] = $this->calculator->faces($node->die, $node->modifiers->reroll, $budget);

                return new Chance(new Fraction(Multisets::weigh($faces, $node->count, $event, $budget), $faces->total->power($node->count)));
            } catch (TooLarge) {
                return new Chance(new Fraction(Natural::of($this->sampler->hits($node, $event)), Natural::of($this->settings->samples)), $this->estimate(EstimateReason::TooLarge));
            } catch (Unsupported) {
                return new Chance(new Fraction(Natural::of($this->sampler->hits($node, $event)), Natural::of($this->settings->samples)), $this->estimate(EstimateReason::Unsupported));
            }
        } catch (CannotRoll $cannotRoll) {
            throw CannotCompute::cannotRoll($cannotRoll);
        }
    }

    /**
     * @throws CannotCompute when the pool holds several expressions
     * @throws LimitExceeded when the expression goes past a limit
     */
    private function single(Node|Pool $expression): Node
    {
        $pool = $expression instanceof Pool ? $expression : new Pool($expression);

        if (\count($pool->expressions) > 1) {
            throw CannotCompute::severalExpressions(\count($pool->expressions));
        }

        $this->limits->check($pool);

        return $pool->expressions[0];
    }

    private function estimate(EstimateReason $reason): Estimate
    {
        return new Estimate($this->settings->samples, $this->settings->seed, $reason);
    }

    /**
     * Whether the dice that count show the faces as they fell, rerolls aside: no explosion, floor, transformation,
     * filter, selection or count changes them.
     */
    private function judgedByFaces(Modifiers $modifiers): bool
    {
        return !$modifiers->unique instanceof Repetition && !$modifiers->explosion instanceof Explosion && !$modifiers->explodeAllSame
            && null === $modifiers->minimum && null === $modifiers->maximum && [] === $modifiers->transforms
            && !$modifiers->filter instanceof Condition && !$modifiers->selection instanceof Selection && !$modifiers->success instanceof Condition
            && !$modifiers->occurrences instanceof Occurrences && null === $modifiers->groups;
    }
}
