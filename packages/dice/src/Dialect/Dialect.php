<?php

declare(strict_types=1);

namespace Tilttek\Dice\Dialect;

use Override;
use RoundingMode;
use Tilttek\Dice\Die\CustomDie;
use Tilttek\Dice\Die\FudgeDie;
use Tilttek\Dice\Die\NumericDie;
use Tilttek\Dice\Die\RangeDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Expression\Choice;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Conditional;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\Group;
use Tilttek\Dice\Expression\InstructionDice;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Operator;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\PreviousDice;
use Tilttek\Dice\Expression\Reference;
use Tilttek\Dice\Expression\Scope;
use Tilttek\Dice\Expression\Sentence;
use Tilttek\Dice\Expression\TextList;
use Tilttek\Dice\Expression\Validator;
use Tilttek\Dice\Expression\ValueList;
use Tilttek\Dice\Notation\ExpressionWriter;
use Tilttek\Dice\Notation\NotationWriter;

/**
 * The notation of a virtual tabletop, which reads some constructs differently: each dialect writes the dice and their
 * modifiers its way, and reports what it cannot write instead of writing it wrong.
 *
 * A dialect writes the divisions rounded down, as a roll does by default; inside `floor()`, `ceil()` and `round()`, a
 * division rounds as the function says. A roll made with another rounding gets a notice: the table rolls it rounded
 * down. Instructions that refer to each other, groups, text lists, sentences, branches and `repeat()` are reported.
 */
abstract class Dialect extends ExpressionWriter
{
    /**
     * @var list<Unsupported>
     */
    private array $unsupported = [];

    /**
     * @var list<string>
     */
    private array $notices = [];

    /**
     * The function that rounds the divisions being written; none outside a rounding function.
     */
    private ?MathFunction $rounding = null;

    /**
     * How the roll being written rounds its divisions.
     */
    private RoundingMode $mode = RoundingMode::NegativeInfinity;

    /**
     * The dialect's name, by which a registry finds it.
     */
    abstract public function name(): string;

    /**
     * @param RoundingMode $rounding how the roll rounds its divisions: any other mode than down gets a notice
     */
    final public function translate(Pool $pool, RoundingMode $rounding = RoundingMode::NegativeInfinity): Translation
    {
        $this->unsupported = [];
        $this->notices = [];
        $this->rounding = null;
        $this->mode = $rounding;
        $expressions = array_map(fn (Node $expression): string => $this->node($expression)[0], $pool->expressions);
        $separator = $this->separator();

        return new Translation($this->name(), $this->command(), null === $separator ? $expressions : [implode($separator, $expressions)], $this->unsupported, $this->notices);
    }

    /**
     * The sign that separates the instructions of one command, for a table that rolls them together; null for one that
     * rolls each expression with its own command.
     */
    protected function separator(): ?string
    {
        return null;
    }

    /**
     * The command that rolls an expression in the table's chat, with its trailing space.
     */
    abstract protected function command(): string;

    /**
     * The modifiers of a set of dice, or of a list of values when the die is null; what the table cannot write is
     * reported.
     */
    abstract protected function modifiers(Modifiers $modifiers, Node $node, ?NumericDie $die): string;

    /**
     * A division, rounded as the function given says, and how tightly it binds.
     *
     * @return array{string, int}
     */
    abstract protected function division(Operation $operation, string $left, string $right, MathFunction $rounding): array;

    #[Override]
    final protected function dice(Dice $dice): string
    {
        $text = $dice->count.'d'.match (true) {
            $dice->die instanceof StandardDie => $dice->die->sides,
            $dice->die instanceof FudgeDie => $this->fudge($dice, $dice->die),
            $dice->die instanceof RangeDie => $this->rangeDie($dice, $dice->die),
            $dice->die instanceof CustomDie => $this->unsupported($dice, 'custom dice are declared by each site', '{'.$dice->die->name.'}'),
            default => throw InvalidDefinition::because(\sprintf('%s is not a die the library writes.', $dice->die::class)),
        };

        return $text.$this->modifiers($dice->modifiers, $dice, $dice->die instanceof NumericDie ? $dice->die : null);
    }

    /**
     * Fudge dice, which most tables write `dF` and none weighs.
     */
    protected function fudge(Dice $dice, FudgeDie $die): string
    {
        return 1 === $die->variant ? $this->unsupported($dice, 'weighted Fudge dice are not written', 'F.1') : 'F';
    }

    /**
     * A die on a range, which most tables do not write.
     */
    protected function rangeDie(Dice $dice, RangeDie $die): string
    {
        return $this->unsupported($dice, 'a die on a range is not written', '['.$die->from.'..'.$die->to.']');
    }

    #[Override]
    protected function computedDice(ComputedDice $dice): string
    {
        return $this->unsupported($dice, 'a count or a number of sides rolled first is not written');
    }

    #[Override]
    protected function valueList(ValueList $list): string
    {
        return $this->unsupported($list, 'a list of values is not written');
    }

    #[Override]
    protected function previousDice(PreviousDice $previous): string
    {
        return $this->unsupported($previous, 'the dice of the set before are not judged again');
    }

    #[Override]
    protected function call(FunctionCall $call): string
    {
        return $this->unsupported($call, \sprintf('%s() is not written', $call->function->value));
    }

    #[Override]
    final protected function operation(Operation $operation, string $left, string $right): array
    {
        return match ($operation->operator) {
            Operator::Divide => $this->division($operation, $left, $right, $this->roundingOf($operation)),
            Operator::Modulo => $this->modulo($operation, $left, $right),
            Operator::Power => $this->power($operation, $left, $right),
            default => parent::operation($operation, $left, $right),
        };
    }

    #[Override]
    protected function reference(Reference $reference): string
    {
        return $this->unsupported($reference, 'the result of another instruction is not referred to');
    }

    #[Override]
    protected function instructionDice(InstructionDice $dice): string
    {
        return $this->unsupported($dice, 'the dice of other instructions are not judged again');
    }

    #[Override]
    protected function group(Group $group): string
    {
        return $this->unsupported($group, 'a group of expressions is not written');
    }

    #[Override]
    protected function textList(TextList $list): string
    {
        return $this->unsupported($list, 'a text list is not written');
    }

    #[Override]
    protected function sentence(Sentence $sentence): string
    {
        return $this->unsupported($sentence, 'a sentence is not written');
    }

    #[Override]
    protected function conditional(Conditional $conditional, string $subject): string
    {
        return $this->unsupported($conditional, 'an if is not written');
    }

    #[Override]
    protected function choice(Choice $choice, string $subject): string
    {
        return $this->unsupported($choice, 'a switch is not written');
    }

    /**
     * The function that rounds a division: the one around it, or the floor — with a notice when the roll rounds
     * another way.
     */
    private function roundingOf(Operation $operation): MathFunction
    {
        if (!$this->rounding instanceof MathFunction && RoundingMode::NegativeInfinity !== $this->mode) {
            $notice = \sprintf('%s is written rounded down; the roll rounds it %s.', new NotationWriter()->writeNode($operation), $this->mode->name);

            if (!\in_array($notice, $this->notices, true)) {
                $this->notices[] = $notice;
            }
        }

        return $this->rounding ?? MathFunction::Floor;
    }

    /**
     * A remainder, as the tables whose `%` keeps the sign of the dividend write it: plainly when neither operand can be
     * negative, through a second remainder when the divisor is a constant, and reported otherwise.
     *
     * @return array{string, int}
     */
    protected function modulo(Operation $operation, string $left, string $right): array
    {
        if ($this->nonNegative($operation->left) && $this->nonNegative($operation->right)) {
            return [$left.'%'.$right, Operator::Modulo->precedence()];
        }

        if ($operation->right instanceof Constant) {
            return ['('.$left.'%'.$right.'+'.$right.')%'.$right, Operator::Modulo->precedence()];
        }

        return [$this->unsupported($operation, 'a remainder of a negative value has the sign of the dividend'), self::ATOM];
    }

    /**
     * A power, which the tables that write one write `**`, between parentheses: with an exponent that cannot be
     * negative, the power is an integer.
     *
     * @return array{string, int}
     */
    protected function power(Operation $operation, string $left, string $right): array
    {
        return [$this->nonNegative($operation->right) ? '('.$left.'**'.$right.')' : $this->unsupported($operation, 'a power whose exponent may be negative is not written'), self::ATOM];
    }

    /**
     * A function the table writes by the same name, a rounding function writing its argument with the divisions inside
     * it rounded its way: the division alone when the function wraps nothing else.
     */
    final protected function function(FunctionCall $call): string
    {
        if (MathFunction::Repeat === $call->function) {
            return $this->unsupported($call, 'repeat() is not written');
        }

        $arguments = $this->rounded($call);
        $argument = $call->arguments[0];

        if ($call->function->rounding() instanceof RoundingMode && $argument instanceof Operation && Operator::Divide === $argument->operator) {
            return $arguments[0];
        }

        return $call->function->value.'('.implode(', ', $arguments).')';
    }

    /**
     * The arguments of a function, the divisions inside a rounding function rounded its way.
     *
     * @return list<string>
     */
    final protected function rounded(FunctionCall $call): array
    {
        $rounding = $this->rounding;
        $this->rounding = $call->function->rounding() instanceof RoundingMode ? $call->function : $rounding;
        $arguments = $this->arguments($call);
        $this->rounding = $rounding;

        return $arguments;
    }

    /**
     * Whether a node gives no negative value, as far as its form shows.
     */
    final protected function nonNegative(Node $node): bool
    {
        return match (true) {
            $node instanceof Constant => $node->value >= 0,
            $node instanceof Dice => $node->die instanceof StandardDie && !$node->modifiers->failure instanceof Comparison && [] === $node->modifiers->transforms && (null === $node->modifiers->minimum || $node->modifiers->minimum >= 0),
            $node instanceof Operation => \in_array($node->operator, [Operator::Add, Operator::Multiply, Operator::Divide, Operator::Modulo, Operator::Power], true) && $this->nonNegative($node->left) && $this->nonNegative($node->right),
            $node instanceof FunctionCall => MathFunction::Abs === $node->function || [] === array_filter($node->arguments, fn (Node $argument): bool => !$this->nonNegative($argument)),
            default => false,
        };
    }

    /**
     * The validator, when it is a comparison written with its sign alone; null for any other.
     */
    final protected function plain(?Validator $validator): ?Comparison
    {
        return $validator instanceof Comparison && $validator->isPlain() ? $validator : null;
    }

    /**
     * Successes judged on each final value by a plain comparison, the only ones a table writes.
     */
    final protected function plainSuccess(Condition $success): ?Comparison
    {
        return Scope::Value === $success->scope ? $this->plain($success->validator) : null;
    }

    /**
     * The comparison a critical mark falls on: its own, or the highest or the lowest face when it gives none.
     */
    final protected function bound(?Validator $on, ?NumericDie $die, bool $highest): ?Comparison
    {
        if ($on instanceof Validator) {
            return $this->plain($on);
        }

        return $die instanceof NumericDie ? new Comparison(Comparator::Equal, $highest ? $die->highest() : $die->lowest()) : null;
    }

    /**
     * Records a construct the dialect cannot write, and gives what stands in its place in the text.
     */
    final protected function unsupported(Node $node, string $reason, string $text = ''): string
    {
        $this->unsupported[] = new Unsupported(new NotationWriter()->writeNode($node), $reason);

        return $text;
    }

    /**
     * Reports each modifier the dialect cannot write that is given, by what the report calls it.
     *
     * @param array<string, bool> $modifiers
     */
    final protected function unwritten(Node $node, array $modifiers): void
    {
        foreach (array_keys(array_filter($modifiers)) as $modifier) {
            $this->unsupported($node, $modifier.' is not written');
        }
    }
}
