<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Computation;

use Closure;
use Random\Randomizer;
use RoundingMode;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Die\CustomDie;
use Tilttek\Dice\Die\DieType;
use Tilttek\Dice\Die\FudgeDie;
use Tilttek\Dice\Die\NumericDie;
use Tilttek\Dice\Die\RangeDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Explosion;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Negation;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Occurrences;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Operator;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\Repetition;
use Tilttek\Dice\Expression\Reroll;
use Tilttek\Dice\Expression\Scope;
use Tilttek\Dice\Expression\Selection;
use Tilttek\Dice\Expression\ValueList;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Rolling\Arithmetic;
use Tilttek\Dice\Rolling\Roller;
use Tilttek\DiceProbability\Exact\Fraction;
use Tilttek\DiceProbability\Exact\Natural;
use Tilttek\DiceProbability\Exception\CannotCompute;

/**
 * The exact distribution of an expression, node by node: constants, arithmetic, functions, dice and their modifiers.
 * A sum of dice is a convolution; a selection runs over the sorted multisets of the dice, never over every roll; an
 * explosion is followed to a depth, the probability of going further kept as what remains.
 *
 * @internal
 */
final readonly class Calculator
{
    public function __construct(
        private CustomDice $customDice,
        private Limits $limits,
        private int $depth,
    ) {
    }

    /**
     * @throws Unsupported   when the expression holds a construct the computation does not follow
     * @throws TooLarge      when the computation goes past its budget
     * @throws CannotCompute when the faces of a die carry no value
     * @throws CannotRoll    when an outcome cannot be rolled
     */
    public function evaluate(Node $node, RoundingMode $rounding, Budget $budget): Weights
    {
        return match (true) {
            $node instanceof Constant => Weights::constant($node->value),
            $node instanceof Negation => $this->evaluate($node->operand, $rounding, $budget)->map(static fn (int $value): int => Arithmetic::integer(-$value), $budget),
            $node instanceof Operation => $this->operation($node, $rounding, $budget),
            $node instanceof FunctionCall => $this->call($node, $rounding, $budget),
            $node instanceof Dice => $this->dice($node, $rounding, $budget),
            $node instanceof ComputedDice => $this->computed($node, $rounding, $budget),
            $node instanceof ValueList => $this->values($node),
            default => throw new Unsupported(\sprintf('%s is not followed exactly.', $node::class)),
        };
    }

    /**
     * The weights of the faces of one die of a set — after its reroll — and those of a die rolled again by an
     * explosion.
     *
     * @return array{Weights, Weights}
     *
     * @throws Unsupported when the die's faces cannot be listed
     */
    public function faces(DieType $die, ?Reroll $reroll, Budget $budget): array
    {
        $faces = $this->base($die);
        $budget->spend(\count($faces->weights));

        return [$reroll instanceof Reroll ? $this->reroll($faces, $reroll, $budget) : $faces, $faces];
    }

    private function operation(Operation $operation, RoundingMode $rounding, Budget $budget): Weights
    {
        $left = $this->evaluate($operation->left, $rounding, $budget);
        $right = $this->evaluate($operation->right, $rounding, $budget);

        return $left->combine($right, match ($operation->operator) {
            Operator::Add => Arithmetic::add(...),
            Operator::Subtract => Arithmetic::subtract(...),
            Operator::Multiply => Arithmetic::multiply(...),
            Operator::Divide => static fn (int $a, int $b): int => Arithmetic::divide($a, $b, $rounding),
            Operator::Modulo => Arithmetic::modulo(...),
            Operator::Power => static fn (int $a, int $b): int => Arithmetic::power($a, $b, $rounding),
        }, $budget);
    }

    private function call(FunctionCall $call, RoundingMode $rounding, Budget $budget): Weights
    {
        if (MathFunction::Repeat === $call->function) {
            throw new Unsupported('repeat() gives a list, not a total.');
        }

        $inner = $call->function->rounding() ?? $rounding;
        $arguments = array_map(fn (Node $argument): Weights => $this->evaluate($argument, $inner, $budget), $call->arguments);
        $first = array_shift($arguments);

        return match ($call->function) {
            MathFunction::Abs => $first->map(static fn (int $value): int => Arithmetic::integer(abs($value)), $budget),
            MathFunction::Min => array_reduce($arguments, static fn (Weights $carry, Weights $next): Weights => $carry->combine($next, static fn (int $left, int $right): int => min($left, $right), $budget), $first),
            MathFunction::Max => array_reduce($arguments, static fn (Weights $carry, Weights $next): Weights => $carry->combine($next, static fn (int $left, int $right): int => max($left, $right), $budget), $first),
            default => $first,
        };
    }

    private function computed(ComputedDice $computed, RoundingMode $rounding, Budget $budget): Weights
    {
        $counts = $this->evaluate($computed->count, $rounding, $budget);
        $die = $computed->die;
        $sides = $die instanceof Node ? $this->evaluate($die, $rounding, $budget) : null;

        return $counts->mixture(function (int $count) use ($computed, $die, $sides, $rounding, $budget): Weights {
            $this->limits->checkDice($count);

            if (!$sides instanceof Weights) {
                return $this->dice($this->set($count, $die, $computed->modifiers), $rounding, $budget);
            }

            return $sides->mixture(function (int $side) use ($count, $computed, $rounding, $budget): Weights {
                $this->limits->checkSides($side);

                return $this->dice($this->set($count, $side, $computed->modifiers), $rounding, $budget);
            }, $budget);
        }, $budget);
    }

    /**
     * @throws CannotRoll when the dice are invalid once their count and sides are known
     */
    private function set(int $count, int|DieType $die, Modifiers $modifiers): Dice
    {
        try {
            return new Dice($count, \is_int($die) ? new StandardDie($die) : $die, $modifiers);
        } catch (InvalidDefinition $invalidDefinition) {
            throw CannotRoll::invalidDice($invalidDefinition->getMessage());
        }
    }

    /**
     * A list of values is no chance: its value, judged by its modifiers as a roll would.
     */
    private function values(ValueList $list): Weights
    {
        $result = new Roller($this->limits, $this->customDice)->roll(new Pool($list), new Randomizer());

        return Weights::constant($result->results[0]->result->value());
    }

    private function dice(Dice $dice, RoundingMode $rounding, Budget $budget): Weights
    {
        $die = $dice->die;

        if (!$die instanceof NumericDie) {
            // A custom die takes no modifier: its faces summed.
            return $this->base($die)->sumOf($dice->count, $budget);
        }

        $modifiers = $dice->modifiers;

        if ($modifiers->unique instanceof Repetition || $modifiers->explodeAllSame || $modifiers->occurrences instanceof Occurrences || null !== $modifiers->groups || ($modifiers->filter instanceof Condition && !$modifiers->filter->scope->eachDie())) {
            throw new Unsupported('Unique dice, dice all the same that explode, occurrences, groups and filters on the whole set are not followed exactly.');
        }

        $limit = $modifiers->explosion?->limit;

        if (!$limit instanceof Node) {
            return $this->roll($dice, $die, null, $budget);
        }

        return $this->evaluate($limit, $rounding, $budget)->mixture(fn (int $explosions): Weights => $this->roll($dice, $die, $explosions, $budget), $budget);
    }

    /**
     * The value of a set of numeric dice, its explosions bounded by a limit when one is given.
     */
    private function roll(Dice $dice, NumericDie $die, ?int $limit, Budget $budget): Weights
    {
        $modifiers = $dice->modifiers;
        [$first, $again] = $this->faces($die, $modifiers->reroll, $budget);
        $judge = $this->judge($modifiers);
        $contribution = static fn (int $natural): int => $judge($natural)[1] ?? 0;
        $explosion = $modifiers->explosion;
        $remaining = Fraction::zero();
        $unit = $first;

        if ($explosion instanceof Explosion) {
            if (!$explosion->compound && $modifiers->selection instanceof Selection) {
                throw new Unsupported('A selection among dice that explode into dice of their own is not followed exactly.');
            }

            $highest = $die->highest();
            $trigger = static fn (int $face): bool => $explosion->triggers($face, $highest);
            $added = static fn (int $face): int => $explosion->penetrating ? $face - 1 : $face;
            $cap = min($limit ?? $this->depth, $this->depth);
            // A compound explosion adds its rolls to one die; a spread one rolls dice of their own, each judged apart.
            $unit = $this->chain($first, $again, $trigger, $added, $cap, $explosion->compound ? static fn (int $natural): int => $natural : $contribution, $budget);

            if (null === $limit || $limit > $this->depth) {
                $cut = new Fraction($first->weightOf($trigger), $first->total)->multiply(new Fraction($again->weightOf($trigger), $again->total)->power($cap));
                $remaining = Fraction::one()->subtract($cut->complement()->power($dice->count))->reduced();
            }
        }

        $sum = match (true) {
            $explosion instanceof Explosion && !$explosion->compound => $unit->sumOf($dice->count, $budget),
            $modifiers->selection instanceof Selection => $this->select($unit, $dice->count, $modifiers->selection, $judge, $budget),
            default => $unit->map($contribution, $budget)->sumOf($dice->count, $budget),
        };
        $result = $sum->map($this->final($modifiers), $budget);

        return Weights::of($result->weights, Weights::either($result->remaining, $remaining));
    }

    /**
     * What one die and its explosions add up to: the first face, then a face rolled again each time the last one
     * triggers, up to the cap. Each die is turned into what it adds by the step: its natural value for a compound
     * explosion, what it contributes for a spread one.
     *
     * @param Closure(int): bool $trigger
     * @param Closure(int): int  $added   the value a rolled face adds: one less when the explosion penetrates
     * @param Closure(int): int  $step
     */
    private function chain(Weights $first, Weights $again, Closure $trigger, Closure $added, int $cap, Closure $step, Budget $budget): Weights
    {
        // What the explosions still add once a die has triggered, after k explosions: nothing once the cap is reached.
        $tail = Weights::constant(0);

        for ($done = $cap - 1; $done >= 0; --$done) {
            $next = $tail;
            $tail = $again->mixture(static function (int $face) use ($trigger, $added, $step, $next, $budget): Weights {
                $value = $step($added($face));

                return $trigger($face) ? $next->map(static fn (int $rest): int => $rest + $value, $budget) : Weights::constant($value);
            }, $budget);
        }

        return $first->mixture(static function (int $face) use ($trigger, $step, $tail, $budget): Weights {
            $value = $step($face);

            return $trigger($face) ? $tail->map(static fn (int $rest): int => $rest + $value, $budget) : Weights::constant($value);
        }, $budget);
    }

    /**
     * What a die contributes once judged, from its natural value — the faces it fell on —: its final value after its
     * floor, its ceiling and its transformation, and what it adds to the set: that value, a success, a failure, or
     * a part of a judgement of the whole set. Null when the filter leaves it out.
     *
     * @return Closure(int): ?array{int, int}
     */
    private function judge(Modifiers $modifiers): Closure
    {
        return static function (int $natural) use ($modifiers): ?array {
            $value = max($natural, $modifiers->minimum ?? $natural);
            $value = min($value, $modifiers->maximum ?? $value);

            foreach ($modifiers->transforms as $transform) {
                if (self::matches($transform->when, $natural, $value)) {
                    $value = $transform->value;

                    break;
                }
            }

            if ($modifiers->filter instanceof Condition && !self::matches($modifiers->filter, $natural, $value)) {
                return null;
            }

            $success = $modifiers->success;

            if (!$success instanceof Condition || Scope::Total === $success->scope) {
                return [$value, $value];
            }

            // A judgement of all the dice counts those that fail it: the set passes when none does.
            $hit = self::matches($success, $natural, $value) !== (Scope::All === $success->scope);

            return [$value, $hit ? 1 : (true === $modifiers->failure?->matches($value) ? -1 : 0)];
        };
    }

    /**
     * The value of the set from the sum of what its dice contribute: the sum itself, or 1 and 0 for a judgement of
     * the whole set.
     *
     * @return Closure(int): int
     */
    private function final(Modifiers $modifiers): Closure
    {
        $success = $modifiers->success;

        return static fn (int $sum): int => match ($success?->scope) {
            Scope::All => 0 === $sum ? 1 : 0,
            Scope::Any => $sum > 0 ? 1 : 0,
            Scope::Total => $success->validator->matches($sum) ? 1 : 0,
            default => $sum,
        };
    }

    private static function matches(Condition $condition, int $natural, int $value): bool
    {
        return $condition->validator->matches(Scope::Die === $condition->scope ? $natural : $value);
    }

    /**
     * The sum of what the dice a selection keeps contribute, over the sorted multisets of the dice: the values are
     * taken from the first named — the highest for `kh` and `dh`, the lowest for `kl` and `dl` — and a value shown by
     * several dice is chosen as many times at once, weighed by the number of ways to pick those dice.
     *
     * @param Closure(int): ?array{int, int} $judge
     */
    private function select(Weights $die, int $count, Selection $selection, Closure $judge, Budget $budget): Weights
    {
        $absent = Natural::zero();
        /** @var array<int, array{Natural, int, bool}> $groups */
        $groups = [];

        foreach ($die->weights as $natural => $weight) {
            $judged = $judge($natural);

            if (null === $judged) {
                $absent = $absent->add($weight);

                continue;
            }

            [$value, $contribution] = $judged;

            if (isset($groups[$value]) && $groups[$value][1] !== $contribution) {
                throw new Unsupported('Dice of the same value that contribute differently are not selected exactly.');
            }

            $groups[$value] = [($groups[$value][0] ?? Natural::zero())->add($weight), $contribution, true];
        }

        $selection->mode->highest() ? krsort($groups) : ksort($groups);
        $binomials = Multisets::binomials($count);
        // By dice placed and dice named so far: the weights of the sums kept.
        $states = [0 => [0 => [0 => Natural::one()]]];
        // The dice the filter leaves out are no candidates: they are named by no selection, and contribute nothing.
        $categories = [[$absent, 0, false], ...$groups];

        foreach ($categories as [$weight, $contribution, $candidate]) {
            if ($weight->isZero()) {
                continue;
            }

            $powers = [Natural::one()];

            for ($dice = 1; $dice <= $count; ++$dice) {
                $powers[$dice] = $powers[$dice - 1]->multiply($weight);
            }

            $next = [];

            foreach ($states as $placed => $byNamed) {
                foreach ($byNamed as $named => $sums) {
                    for ($dice = 0; $dice <= $count - $placed; ++$dice) {
                        $budget->spend(\count($sums));
                        $factor = $binomials[$count - $placed][$dice]->multiply($powers[$dice]);
                        $newlyNamed = $candidate ? min($dice, $selection->count - $named) : 0;
                        $kept = $selection->mode->keeps() ? $newlyNamed : $dice - $newlyNamed;

                        foreach ($sums as $sum => $ways) {
                            $key = $sum + $kept * $contribution;
                            $ways = $ways->multiply($factor);
                            $current = $next[$placed + $dice][$named + $newlyNamed][$key] ?? null;
                            $next[$placed + $dice][$named + $newlyNamed][$key] = $current?->add($ways) ?? $ways;
                        }
                    }
                }
            }

            $states = $next;
        }

        $weights = [];

        foreach ($states[$count] ?? [] as $sums) {
            foreach ($sums as $sum => $ways) {
                $weights[$sum] = isset($weights[$sum]) ? $weights[$sum]->add($ways) : $ways;
            }
        }

        return Weights::of($weights, $die->remaining);
    }

    /**
     * The faces of a die, each as likely as the others.
     *
     * @throws Unsupported   when the die is none the computation knows the faces of
     * @throws CannotCompute when the faces of a custom die carry no value
     */
    private function base(DieType $die): Weights
    {
        return match (true) {
            $die instanceof StandardDie => Weights::faces(range(1, $die->sides)),
            $die instanceof RangeDie => Weights::faces(range($die->from, $die->to)),
            $die instanceof FudgeDie => Weights::faces(2 === $die->variant ? [-1, 0, 1] : [-1, 0, 0, 0, 0, 1]),
            $die instanceof CustomDie => $this->custom($die),
            default => throw new Unsupported(\sprintf('The faces of %s are not known.', $die::class)),
        };
    }

    private function custom(CustomDie $die): Weights
    {
        $definition = $this->customDice->get($die->name);

        if (!$definition->numeric()) {
            throw CannotCompute::notNumeric($die->name);
        }

        $values = [];

        foreach ($definition->faces as $face) {
            if (null !== $face->value) {
                $values[] = $face->value;
            }
        }

        return Weights::faces($values);
    }

    /**
     * The faces of a die once its reroll applies: a face that matches is rolled again until it no longer matches, or
     * once.
     */
    private function reroll(Weights $faces, Reroll $reroll, Budget $budget): Weights
    {
        $budget->spend(\count($faces->weights));

        if (Repetition::Repeated === $reroll->repetition) {
            return Weights::of(array_filter($faces->weights, static fn (int $face): bool => !$reroll->on->matches($face), \ARRAY_FILTER_USE_KEY));
        }

        $rerolled = $faces->weightOf($reroll->on->matches(...));
        $weights = [];

        foreach ($faces->weights as $face => $weight) {
            $weights[$face] = $weight->multiply($rerolled);

            if (!$reroll->on->matches($face)) {
                $weights[$face] = $weights[$face]->add($weight->multiply($faces->total));
            }
        }

        return Weights::of($weights);
    }
}
