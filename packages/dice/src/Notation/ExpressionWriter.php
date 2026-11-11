<?php

declare(strict_types=1);

namespace Tilttek\Dice\Notation;

use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Expression\Choice;
use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Conditional;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\Group;
use Tilttek\Dice\Expression\InstructionDice;
use Tilttek\Dice\Expression\Negation;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Operator;
use Tilttek\Dice\Expression\PreviousDice;
use Tilttek\Dice\Expression\Reference;
use Tilttek\Dice\Expression\Sentence;
use Tilttek\Dice\Expression\TextList;
use Tilttek\Dice\Expression\ValueList;

/**
 * Writes the arithmetic of an expression with no more parentheses than its tree needs; each writer says how it writes
 * dice, lists, functions, the dice before, references, groups, text lists, sentences and branches, and may write an
 * operation its own way. The subject of a branch is put between parentheses unless it is an operand alone.
 */
abstract class ExpressionWriter
{
    protected const int NEGATION = 3;

    protected const int ATOM = 5;

    /**
     * The text of a node and how tightly it binds, to decide whether a parent puts it between parentheses.
     *
     * @return array{string, int}
     */
    final protected function node(Node $node): array
    {
        if ($node instanceof Operation) {
            return $this->operation($node, ...$this->operands($node));
        }

        if ($node instanceof Negation) {
            [$operand, $precedence] = $this->node($node->operand);

            return ['-'.($precedence < self::NEGATION ? '('.$operand.')' : $operand), self::NEGATION];
        }

        if ($node instanceof Conditional || $node instanceof Choice) {
            [$subject, $precedence] = $this->node($node->subject);
            $subject = $precedence < self::ATOM ? '('.$subject.')' : $subject;

            return [$node instanceof Conditional ? $this->conditional($node, $subject) : $this->choice($node, $subject), self::ATOM];
        }

        return match (true) {
            $node instanceof Dice => [$this->dice($node), self::ATOM],
            $node instanceof ComputedDice => [$this->computedDice($node), self::ATOM],
            $node instanceof ValueList => [$this->valueList($node), self::ATOM],
            $node instanceof PreviousDice => [$this->previousDice($node), self::ATOM],
            $node instanceof FunctionCall => [$this->call($node), self::ATOM],
            $node instanceof Reference => [$this->reference($node), self::ATOM],
            $node instanceof InstructionDice => [$this->instructionDice($node), self::ATOM],
            $node instanceof Group => [$this->group($node), self::ATOM],
            $node instanceof TextList => [$this->textList($node), self::ATOM],
            $node instanceof Sentence => [$this->sentence($node), self::ATOM],
            // A negative constant binds as a negation: a power puts it between parentheses.
            $node instanceof Constant => [\sprintf('%d', $node->value), $node->value < 0 ? self::NEGATION : self::ATOM],
            default => throw InvalidDefinition::because(\sprintf("The node %s is not one of the library's.", $node::class)),
        };
    }

    /**
     * An operation whose operands are already between parentheses where they need them.
     *
     * @return array{string, int}
     */
    protected function operation(Operation $operation, string $left, string $right): array
    {
        return [$left.$operation->operator->value.$right, $operation->operator->precedence()];
    }

    /**
     * The text of each argument of a function.
     *
     * @return list<string>
     */
    final protected function arguments(FunctionCall $call): array
    {
        return array_map(fn (Node $argument): string => $this->node($argument)[0], $call->arguments);
    }

    abstract protected function dice(Dice $dice): string;

    abstract protected function computedDice(ComputedDice $dice): string;

    abstract protected function valueList(ValueList $list): string;

    abstract protected function previousDice(PreviousDice $previous): string;

    abstract protected function call(FunctionCall $call): string;

    abstract protected function reference(Reference $reference): string;

    abstract protected function instructionDice(InstructionDice $dice): string;

    abstract protected function group(Group $group): string;

    abstract protected function textList(TextList $list): string;

    abstract protected function sentence(Sentence $sentence): string;

    /**
     * An `if`, after its subject, already between parentheses where it needs them.
     */
    abstract protected function conditional(Conditional $conditional, string $subject): string;

    /**
     * A `switch`, after its subject, already between parentheses where it needs them.
     */
    abstract protected function choice(Choice $choice, string $subject): string;

    /**
     * Both operands, between parentheses where they need them: operations of the left bind left to right, a power
     * from right to left, and its exponent may be negated.
     *
     * @return array{string, string}
     */
    private function operands(Operation $operation): array
    {
        $precedence = $operation->operator->precedence();
        [$left, $leftPrecedence] = $this->node($operation->left);
        [$right, $rightPrecedence] = $this->node($operation->right);

        if (Operator::Power === $operation->operator) {
            return [$leftPrecedence > $precedence ? $left : '('.$left.')', $rightPrecedence >= self::NEGATION ? $right : '('.$right.')'];
        }

        return [$leftPrecedence < $precedence ? '('.$left.')' : $left, $rightPrecedence > $precedence ? $right : '('.$right.')'];
    }
}
