<?php

declare(strict_types=1);

namespace Tilttek\Dice\Rolling;

use Random\Randomizer;
use RoundingMode;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Die\CustomDie;
use Tilttek\Dice\Die\NumericDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Choice;
use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Conditional;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\Group;
use Tilttek\Dice\Expression\InstructionDice;
use Tilttek\Dice\Expression\ListItem;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Negation;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Operator;
use Tilttek\Dice\Expression\Placeholder;
use Tilttek\Dice\Expression\PlaceholderKind;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\PreviousDice;
use Tilttek\Dice\Expression\Range;
use Tilttek\Dice\Expression\Reference;
use Tilttek\Dice\Expression\Scope;
use Tilttek\Dice\Expression\Sentence;
use Tilttek\Dice\Expression\SwitchCase;
use Tilttek\Dice\Expression\TextList;
use Tilttek\Dice\Expression\ValueList;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Result\BranchResult;
use Tilttek\Dice\Result\ConstantResult;
use Tilttek\Dice\Result\DiceResult;
use Tilttek\Dice\Result\ExpressionResult;
use Tilttek\Dice\Result\FunctionResult;
use Tilttek\Dice\Result\ListResult;
use Tilttek\Dice\Result\NegationResult;
use Tilttek\Dice\Result\NodeResult;
use Tilttek\Dice\Result\OperationResult;
use Tilttek\Dice\Result\Outputs;
use Tilttek\Dice\Result\PoolResult;
use Tilttek\Dice\Result\ReferenceResult;
use Tilttek\Dice\Result\RolledDie;
use Tilttek\Dice\Result\TextResult;

/**
 * Rolls a pool with the randomness it is given: a secure engine in production, a fixed seed in tests, the seed of a
 * verifiable or replayable roll. The same pool rolled with the same seed and the same rounding gives the same result,
 * die for die.
 *
 * Before any die is rolled, the pool is checked against the limits, its custom dice are found, and custom dice whose
 * faces carry no value are refused inside arithmetic. Dice, draws, explosions, rerolls and repetitions are counted
 * across the whole pool; those whose count is rolled are counted once it is known. The instructions are rolled in
 * order, each seeing the results of those before it; only the branch that holds is rolled.
 */
final readonly class Roller
{
    public function __construct(
        private Limits $limits = new Limits(),
        private CustomDice $customDice = new CustomDice(),
    ) {
    }

    /**
     * @param RoundingMode $rounding how a division rounds, unless `floor()`, `ceil()` or `round()` say otherwise:
     *                               down by default; up for one side and down for the other when a game says so
     *
     * @throws LimitExceeded when the pool, its rolled counts, its explosions or its rerolls go past a limit
     * @throws CannotRoll    when a die is unknown, faces without value enter arithmetic, rolled dice cannot be rolled,
     *                       or the arithmetic fails
     */
    public function roll(Pool $pool, Randomizer $randomizer, RoundingMode $rounding = RoundingMode::NegativeInfinity): PoolResult
    {
        $this->limits->check($pool);

        foreach ($pool->expressions as $expression) {
            $this->check($expression, false);
        }

        $context = new Context($randomizer, $rounding, $this->limits);

        foreach ($pool->expressions as $expression) {
            $context->forget();
            $context->instructions[] = $this->evaluate($expression, $context);
        }

        $results = [];

        foreach ($pool->expressions as $index => $expression) {
            $results[] = new ExpressionResult($expression, $context->instructions[$index], $context->merged[$index + 1] ?? null);
        }

        return new PoolResult($pool, $results, $rounding);
    }

    private function check(Node $node, bool $arithmetic): void
    {
        foreach ($this->children($node) as [$child, $computed]) {
            $this->check($child, $computed);
        }

        $die = $node instanceof Dice || $node instanceof ComputedDice ? $node->die : null;

        if ($die instanceof CustomDie && !$this->customDice->get($die->name)->numeric() && $arithmetic) {
            throw CannotRoll::notNumeric($die->name);
        }
    }

    /**
     * The nodes a node holds, and whether it computes with them — a branch gives its own as they are.
     *
     * @return list<array{Node, bool}>
     */
    private function children(Node $node): array
    {
        $repeat = $node instanceof FunctionCall && MathFunction::Repeat === $node->function;
        $computed = match (true) {
            $repeat => [$node->arguments[1]],
            $node instanceof Operation => [$node->left, $node->right],
            $node instanceof Negation => [$node->operand],
            $node instanceof FunctionCall => $node->arguments,
            $node instanceof ComputedDice => [$node->count, ...($node->die instanceof Node ? [$node->die] : [])],
            $node instanceof Group => $node->expressions,
            $node instanceof TextList => [$node->count],
            $node instanceof Conditional, $node instanceof Choice => [$node->subject],
            default => [],
        };
        $given = match (true) {
            $repeat => [$node->arguments[0]],
            $node instanceof Conditional => [$node->then, ...($node->else instanceof Node ? [$node->else] : [])],
            $node instanceof Choice => [...array_map(static fn (SwitchCase $case): Node => $case->then, $node->cases), ...($node->default instanceof Node ? [$node->default] : [])],
            default => [],
        };
        $limit = $node instanceof Dice || $node instanceof ComputedDice ? $node->modifiers->explosion?->limit : null;

        return [
            ...array_map(static fn (Node $child): array => [$child, true], [...$computed, ...($limit instanceof Node ? [$limit] : [])]),
            ...array_map(static fn (Node $child): array => [$child, false], $given),
        ];
    }

    private function evaluate(Node $node, Context $context): NodeResult
    {
        return match (true) {
            $node instanceof Operation => $this->operation($node, $context),
            $node instanceof Negation => $this->negation($node, $context),
            $node instanceof FunctionCall => $this->call($node, $context),
            $node instanceof ComputedDice => $this->computed($node, $context),
            $node instanceof Dice => $this->dice($node, $context, []),
            $node instanceof ValueList => $this->values($node, $context),
            $node instanceof PreviousDice => $this->previous($node, $context),
            $node instanceof Constant => new ConstantResult($node->value),
            $node instanceof Reference => new ReferenceResult($node->instruction, $node->index, $this->item($node->instruction, $node->index, $context)),
            $node instanceof InstructionDice => $this->instructionDice($node, $context),
            $node instanceof Group => $this->group($node, $context),
            $node instanceof TextList => $this->textList($node, $context),
            $node instanceof Sentence => $this->sentence($node, $context),
            $node instanceof Conditional => $this->conditional($node, $context),
            $node instanceof Choice => $this->choice($node, $context),
            default => throw InvalidDefinition::because(\sprintf('%s is not a node the library rolls.', $node::class)),
        };
    }

    private function operation(Operation $operation, Context $context): OperationResult
    {
        $left = $this->evaluate($operation->left, $context);
        $right = $this->evaluate($operation->right, $context);
        $value = match ($operation->operator) {
            Operator::Add => Arithmetic::add($left->value(), $right->value()),
            Operator::Subtract => Arithmetic::subtract($left->value(), $right->value()),
            Operator::Multiply => Arithmetic::multiply($left->value(), $right->value()),
            Operator::Divide => Arithmetic::divide($left->value(), $right->value(), $context->rounding),
            Operator::Modulo => Arithmetic::modulo($left->value(), $right->value()),
            Operator::Power => Arithmetic::power($left->value(), $right->value(), $context->rounding),
        };

        return new OperationResult($operation->operator, $left, $right, $value);
    }

    private function negation(Negation $negation, Context $context): NegationResult
    {
        $operand = $this->evaluate($negation->operand, $context);

        return new NegationResult($operand, Arithmetic::integer(-$operand->value()));
    }

    private function call(FunctionCall $call, Context $context): FunctionResult|ListResult
    {
        if (MathFunction::Repeat === $call->function) {
            return $this->repeat($call, $context);
        }

        $rounding = $context->rounding;
        $context->rounding = $call->function->rounding() ?? $rounding;
        $arguments = array_map(fn (Node $argument): NodeResult => $this->evaluate($argument, $context), $call->arguments);
        $context->rounding = $rounding;
        $values = array_map(static fn (NodeResult $argument): int => $argument->value(), $arguments);
        $value = match ($call->function) {
            MathFunction::Abs => Arithmetic::integer(abs($values[0])),
            MathFunction::Min => min($values),
            MathFunction::Max => max($values),
            default => $values[0],
        };

        return new FunctionResult($call->function, $arguments, $value);
    }

    private function computed(ComputedDice $computed, Context $context): DiceResult
    {
        $count = $this->evaluate($computed->count, $context);
        $parts = ['count' => $count];
        $die = $computed->die;

        if ($die instanceof Node) {
            $parts['sides'] = $this->evaluate($die, $context);
            $die = $parts['sides']->value();
            $this->limits->checkSides($die);
        }

        try {
            $dice = new Dice($count->value(), \is_int($die) ? new StandardDie($die) : $die, $computed->modifiers);
        } catch (InvalidDefinition $invalidDefinition) {
            throw CannotRoll::invalidDice($invalidDefinition->getMessage());
        }

        return $this->dice($dice, $context, $parts);
    }

    /**
     * @param array<string, NodeResult> $parts
     */
    private function dice(Dice $dice, Context $context, array $parts): DiceResult
    {
        $limit = $dice->modifiers->explosion?->limit;
        $explosions = null;

        if ($limit instanceof Node) {
            $parts['explosion-limit'] = $this->evaluate($limit, $context);
            $explosions = $parts['explosion-limit']->value();
        }

        $context->roll($dice->count);
        $die = $dice->die;

        if ($die instanceof CustomDie) {
            return $this->rollCustom($dice, $die, $context, $parts);
        }

        if (!$die instanceof NumericDie) {
            throw InvalidDefinition::because(\sprintf('%s is not a die the library rolls.', $die::class));
        }

        [$rolled, $value, $groups, $notices] = SetRoller::judge(SetRoller::roll($dice, $die, $context, $explosions), $dice->modifiers, $die->lowest(), $die->highest());
        $result = new DiceResult($dice, $rolled, $value, groups: $groups, parts: $parts, notices: $notices);
        $context->remember($result, $die->lowest(), $die->highest());

        return $result;
    }

    private function values(ValueList $list, Context $context): DiceResult
    {
        $context->roll(\count($list->values));
        $lowest = min($list->values);
        $highest = max($list->values);
        [$dice, $value, $groups, $notices] = SetRoller::judge(array_map(static fn (int $value): RolledDie => new RolledDie($value), $list->values), $list->modifiers, $lowest, $highest);
        $result = new DiceResult($list, $dice, $value, groups: $groups, notices: $notices);
        $context->remember($result, $lowest, $highest);

        return $result;
    }

    private function previous(PreviousDice $previous, Context $context): DiceResult
    {
        $set = $context->previous ?? throw CannotRoll::nothingBefore();
        [$dice, $value, $groups, $notices] = SetRoller::judge($this->copies($set->kept()), $previous->modifiers, $context->lowest, $context->highest);

        return new DiceResult($previous, $dice, $value, $set->numeric, $groups, notices: $notices);
    }

    /**
     * The dice that count in instructions before, judged again as one set; a merge takes them from their instructions.
     */
    private function instructionDice(InstructionDice $taken, Context $context): DiceResult
    {
        $kept = [];
        $numeric = true;

        foreach ($taken->instructions as $instruction) {
            foreach ($this->instruction($instruction, $context)->sets() as $set) {
                $kept = [...$kept, ...$set->kept()];
                $numeric = $numeric && $set->numeric;
            }

            if ($taken->merge) {
                $context->merged[$instruction] = \count($context->instructions) + 1;
            }
        }

        $values = array_map(static fn (RolledDie $die): int => $die->value, $kept);
        $lowest = [] === $values ? 0 : min($values);
        $highest = [] === $values ? 0 : max($values);
        [$dice, $value, $groups, $notices] = SetRoller::judge($this->copies($kept), $taken->modifiers, $lowest, $highest);
        $result = new DiceResult($taken, $dice, $value, $numeric, $groups, notices: $notices);
        $context->remember($result, $lowest, $highest);

        return $result;
    }

    /**
     * Each expression of a group rolled on its own, its total taken as a die.
     */
    private function group(Group $group, Context $context): DiceResult
    {
        $parts = [];
        $dice = [];

        foreach ($group->expressions as $index => $expression) {
            $context->forget();
            $part = $this->evaluate($expression, $context);
            $parts['expression-'.($index + 1)] = $part;
            $dice[] = new RolledDie($part->value());
        }

        $values = array_map(static fn (RolledDie $die): int => $die->value, $dice);
        [$dice, $value, $groups, $notices] = SetRoller::judge($dice, $group->modifiers, min($values), max($values));
        $result = new DiceResult($group, $dice, $value, groups: $groups, parts: $parts, notices: $notices);
        $context->remember($result, min($values), max($values));

        return $result;
    }

    /**
     * Items drawn from a text list, each by its weight — or by the width of its range, the number rolled being the
     * one on the ranges —; a unique list draws among the items not drawn yet.
     */
    private function textList(TextList $list, Context $context): ListResult
    {
        if ($list->count instanceof Constant) {
            $count = null;
            $draws = $list->count->value;
        } else {
            $count = $this->evaluate($list->count, $context);
            $draws = $count->value();
        }

        try {
            $list->checkCount($draws);
        } catch (InvalidDefinition $invalidDefinition) {
            throw CannotRoll::invalidCount($invalidDefinition->getMessage());
        }

        $context->roll($draws);
        $available = $list->items;
        $items = [];
        $indexes = [];
        $rolls = [];

        for ($draw = 0; $draw < $draws; ++$draw) {
            $keys = array_keys($available);
            $roll = $context->randomizer->getInt(1, array_sum(array_map(static fn (ListItem $item): int => $item->chances(), $available)));
            $index = 0;
            $before = 0;

            while ($before + $available[$keys[$index]]->chances() < $roll) {
                $before += $available[$keys[$index]]->chances();
                ++$index;
            }

            $item = $available[$keys[$index]];
            $items[] = $item->text;
            $indexes[] = $keys[$index];
            $rolls[] = $item->range instanceof Range ? $item->range->from + $roll - $before - 1 : $roll;

            if ($list->unique) {
                unset($available[$keys[$index]]);
            }
        }

        return new ListResult($items, $list->joined, [], $indexes, $rolls, $count);
    }

    /**
     * A sentence, its placeholders replaced by what they speak of.
     */
    private function sentence(Sentence $sentence, Context $context): TextResult
    {
        $text = '';

        foreach ($sentence->parts as $part) {
            $text .= $part instanceof Placeholder ? $this->placeholder($part, $context) : $part;
        }

        $context->text($text);

        return new TextResult($text);
    }

    private function placeholder(Placeholder $placeholder, Context $context): string
    {
        if (null !== $placeholder->instruction) {
            return PlaceholderKind::Result === $placeholder->kind
                ? Outputs::text($this->item($placeholder->instruction, $placeholder->index, $context))
                : implode(', ', self::diceValues($this->instruction($placeholder->instruction, $context)));
        }

        if (PlaceholderKind::Before === $placeholder->kind) {
            return implode(', ', array_map(static fn (NodeResult $result): string => Outputs::text($result->output()), $context->instructions));
        }

        $subject = $context->subject ?? $context->instructions[\count($context->instructions) - 1] ?? throw CannotRoll::noInstruction(1);

        return PlaceholderKind::Subject === $placeholder->kind ? Outputs::text($subject->output()) : implode(', ', self::diceValues($subject));
    }

    private function conditional(Conditional $conditional, Context $context): BranchResult
    {
        $subject = $this->evaluate($conditional->subject, $context);
        $holds = $this->holds($conditional->condition, $subject);
        $branch = $holds ? $conditional->then : $conditional->else;

        return new BranchResult($subject, $holds ? [0] : [], $branch instanceof Node ? $this->branch($branch, $subject, $context) : $subject, !$holds && $branch instanceof Node);
    }

    private function choice(Choice $choice, Context $context): BranchResult
    {
        $subject = $this->evaluate($choice->subject, $context);
        $matched = [];
        $results = [];

        foreach ($choice->cases as $index => $case) {
            if ($this->holds($case->when, $subject)) {
                $matched[] = $index;
                $results[] = $this->branch($case->then, $subject, $context);

                if ($choice->exclusive) {
                    break;
                }
            }
        }

        if ([] === $matched) {
            return new BranchResult($subject, [], $choice->default instanceof Node ? $this->branch($choice->default, $subject, $context) : $subject, $choice->default instanceof Node);
        }

        $items = [];

        foreach ($results as $result) {
            $items = [...$items, ...Outputs::items($result->output())];
        }

        return new BranchResult($subject, $matched, $choice->exclusive ? $results[0] : new ListResult($items, parts: $results));
    }

    /**
     * A branch, rolled with its subject as the one its sentences speak of.
     */
    private function branch(Node $branch, NodeResult $subject, Context $context): NodeResult
    {
        $outer = $context->subject;
        $context->subject = $subject;
        $result = $this->evaluate($branch, $context);
        $context->subject = $outer;

        return $result;
    }

    /**
     * What `repeat()` repeats, rolled as many times as its count says, the outputs in a list.
     */
    private function repeat(FunctionCall $call, Context $context): ListResult
    {
        $count = $this->evaluate($call->arguments[1], $context);
        $times = $count->value();

        if ($times < 1) {
            throw CannotRoll::invalidCount(\sprintf('repeat() rolls at least once; %d is not.', $times));
        }

        $context->repeat($times);
        $parts = [];
        $items = [];

        for ($time = 0; $time < $times; ++$time) {
            $parts[] = $part = $this->evaluate($call->arguments[0], $context);
            $items = [...$items, ...Outputs::items($part->output())];
        }

        return new ListResult($items, parts: $parts, count: $call->arguments[1] instanceof Constant ? null : $count);
    }

    /**
     * Whether a branch's condition holds for its subject: its value, all or any of the dice that count in it — the
     * value alone when it has none —, or their total.
     */
    private function holds(Condition $condition, NodeResult $subject): bool
    {
        if (Scope::Value === $condition->scope || Scope::Die === $condition->scope) {
            return $condition->validator->matches($subject->value());
        }

        $values = [] === $subject->sets() ? [$subject->value()] : self::diceValues($subject);
        $matching = array_filter($values, $condition->validator->matches(...));

        return match ($condition->scope) {
            Scope::All => [] !== $values && \count($matching) === \count($values),
            Scope::Any => [] !== $matching,
            default => $condition->validator->matches(Arithmetic::integer(array_sum($values))),
        };
    }

    /**
     * The result of an instruction before, by its number from 1.
     */
    private function instruction(int $instruction, Context $context): NodeResult
    {
        return $context->instructions[$instruction - 1] ?? throw CannotRoll::noInstruction($instruction);
    }

    /**
     * What an instruction gave, or its item: of its list, or of the values of the dice that count in it.
     *
     * @return int|string|list<int|string>
     */
    private function item(int $instruction, ?int $index, Context $context): int|string|array
    {
        $result = $this->instruction($instruction, $context);

        if (null === $index) {
            return $result->output();
        }

        $items = self::items($result);

        return $items[$index] ?? throw CannotRoll::noItem($instruction, $index, \count($items));
    }

    /**
     * The items of a result: those of its list, joined or not, or the values of the dice that count in it.
     *
     * @return list<int|string>
     */
    private static function items(NodeResult $result): array
    {
        return match (true) {
            $result instanceof BranchResult => self::items($result->result),
            $result instanceof ListResult => $result->items,
            \is_array($result->output()) => $result->output(),
            default => self::diceValues($result),
        };
    }

    /**
     * The values of the dice that count in a result, set after set.
     *
     * @return list<int>
     */
    private static function diceValues(NodeResult $result): array
    {
        $values = [];

        foreach ($result->sets() as $set) {
            foreach ($set->kept() as $die) {
                $values[] = $die->value;
            }
        }

        return $values;
    }

    /**
     * Dice taken again, without their marks, to be judged again.
     *
     * @param list<RolledDie> $dice
     *
     * @return list<RolledDie>
     */
    private function copies(array $dice): array
    {
        return array_map(static fn (RolledDie $die): RolledDie => new RolledDie($die->value, face: $die->face, rolls: $die->rolls), $dice);
    }

    /**
     * @param array<string, NodeResult> $parts
     */
    private function rollCustom(Dice $dice, CustomDie $die, Context $context, array $parts): DiceResult
    {
        $definition = $this->customDice->get($die->name);
        $faces = $definition->faces;
        $rolled = [];
        $total = 0;

        for ($index = 0; $index < $dice->count; ++$index) {
            $face = $faces[$context->randomizer->getInt(0, \count($faces) - 1)];
            $value = $face->value ?? 0;
            $rolled[] = new RolledDie($value, face: $face);
            $total = Arithmetic::add($total, $value);
        }

        $values = array_map(static fn (Face $face): int => (int) $face->value, $faces);
        $result = new DiceResult($dice, $rolled, $total, $definition->numeric(), parts: $parts);
        $context->remember($result, min($values), max($values));

        return $result;
    }
}
