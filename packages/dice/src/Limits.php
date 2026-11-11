<?php

declare(strict_types=1);

namespace Tilttek\Dice;

use Tilttek\Dice\Die\RangeDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\Dice\Expression\Choice;
use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Conditional;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Explosion;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\Group;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Negation;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\SwitchCase;
use Tilttek\Dice\Expression\TextList;
use Tilttek\Dice\Expression\ValueList;

/**
 * The bounds against abuse: the dice an expression rolls, lists or draws, the sides of a die, the explosions, the
 * rerolls and the repetitions of a roll, the instructions of a pool, the characters of an input and of a text it
 * gives. What goes past one is refused, never truncated.
 */
final readonly class Limits
{
    public function __construct(
        public int $dice = 100,
        public int $sides = 1000,
        public int $explosions = 100,
        public int $expressions = 10,
        public int $length = 256,
        public int $rerolls = 100,
        public int $repetitions = 100,
        public int $text = 4096,
    ) {
        foreach (['dice' => $dice, 'sides' => $sides, 'explosions' => $explosions, 'expressions' => $expressions, 'length' => $length, 'rerolls' => $rerolls, 'repetitions' => $repetitions, 'text' => $text] as $name => $limit) {
            if ($limit < 1) {
                throw InvalidDefinition::because(\sprintf('The limit of %s is at least 1; %d is not.', $name, $limit));
            }
        }
    }

    /**
     * @throws LimitExceeded when the pool rolls too many dice, a die with too many sides, too many expressions, or
     *                       repeats too many times, as far as its written counts show
     */
    public function check(Pool $pool): void
    {
        if (\count($pool->expressions) > $this->expressions) {
            throw LimitExceeded::of('expressions of a pool', $this->expressions, \count($pool->expressions));
        }

        $dice = 0;
        $repetitions = 0;

        foreach ($pool->expressions as $expression) {
            $repetitions = self::plus($repetitions, $this->repetitions($expression));
            $this->checkRepetitions($repetitions);
            $dice = self::plus($dice, $this->count($expression));
        }

        $this->checkDice($dice);
    }

    /**
     * @throws LimitExceeded when the input is too long
     */
    public function checkLength(string $input): void
    {
        if (mb_strlen($input) > $this->length) {
            throw LimitExceeded::of('characters of an expression', $this->length, mb_strlen($input));
        }
    }

    /**
     * @throws LimitExceeded when a roll rolls or lists too many dice, those whose count is rolled included
     */
    public function checkDice(int $dice): void
    {
        if ($dice > $this->dice) {
            throw LimitExceeded::of('dice of a roll', $this->dice, $dice);
        }
    }

    /**
     * @throws LimitExceeded when a die has too many sides, its number of sides rolled or not
     */
    public function checkSides(int $sides): void
    {
        if ($sides > $this->sides) {
            throw LimitExceeded::of('sides of a die', $this->sides, $sides);
        }
    }

    /**
     * @throws LimitExceeded when a roll explodes too many times, every kind of explosion counted
     */
    public function checkExplosions(int $explosions): void
    {
        if ($explosions > $this->explosions) {
            throw LimitExceeded::of('explosions of a roll', $this->explosions, $explosions);
        }
    }

    /**
     * @throws LimitExceeded when a roll rerolls too many dice, repeated, once or to make them unique
     */
    public function checkRerolls(int $rerolls): void
    {
        if ($rerolls > $this->rerolls) {
            throw LimitExceeded::of('rerolls of a roll', $this->rerolls, $rerolls);
        }
    }

    /**
     * @throws LimitExceeded when a roll repeats too many times, `repeat()` within `repeat()` counted for each time
     */
    public function checkRepetitions(int $repetitions): void
    {
        if ($repetitions > $this->repetitions) {
            throw LimitExceeded::of('repetitions of a roll', $this->repetitions, $repetitions);
        }
    }

    /**
     * @throws LimitExceeded when a text a roll gives is too long
     */
    public function checkText(int $characters): void
    {
        if ($characters > $this->text) {
            throw LimitExceeded::of('characters of a text', $this->text, $characters);
        }
    }

    /**
     * The dice a node rolls, lists or draws, as far as its written counts show; a branch counts all of its branches.
     */
    private function count(Node $node): int
    {
        if ($node instanceof FunctionCall && MathFunction::Repeat === $node->function) {
            [$repeated, $times] = $node->arguments;

            return self::plus($this->count($times), self::times($times instanceof Constant ? max(0, $times->value) : 1, $this->count($repeated)));
        }

        return match (true) {
            $node instanceof ComputedDice => self::plus($this->count($node->count), $node->die instanceof Node ? $this->count($node->die) : $this->sides($node->die), $this->limit($node->modifiers->explosion)),
            $node instanceof Dice => self::plus($this->sides($node->die), $node->count, $this->limit($node->modifiers->explosion)),
            $node instanceof ValueList => \count($node->values),
            $node instanceof TextList => self::plus($this->count($node->count), $node->count instanceof Constant ? $node->count->value : 0),
            default => self::plus(...array_map($this->count(...), self::children($node))),
        };
    }

    /**
     * The repetitions a node asks for, as far as its written counts show: what `repeat()` repeats counts once for
     * each time.
     */
    private function repetitions(Node $node): int
    {
        if ($node instanceof FunctionCall && MathFunction::Repeat === $node->function && $node->arguments[1] instanceof Constant) {
            $times = max(0, $node->arguments[1]->value);

            return self::plus($times, self::times($times, $this->repetitions($node->arguments[0])));
        }

        return self::plus(...array_map($this->repetitions(...), self::children($node)));
    }

    /**
     * The nodes a node holds, besides the dice, the counts and the lists counted on their own: a count is a number,
     * never a repetition.
     *
     * @return list<Node>
     */
    private static function children(Node $node): array
    {
        return match (true) {
            $node instanceof Operation => [$node->left, $node->right],
            $node instanceof Negation => [$node->operand],
            $node instanceof FunctionCall => $node->arguments,
            $node instanceof Group => $node->expressions,
            $node instanceof Conditional => [$node->subject, $node->then, ...($node->else instanceof Node ? [$node->else] : [])],
            $node instanceof Choice => [$node->subject, ...array_map(static fn (SwitchCase $case): Node => $case->then, $node->cases), ...($node->default instanceof Node ? [$node->default] : [])],
            default => [],
        };
    }

    /**
     * A sum of counts, none below zero, that stops at the largest integer.
     */
    private static function plus(int ...$terms): int
    {
        $sum = 0;

        foreach ($terms as $term) {
            $sum = $term > \PHP_INT_MAX - $sum ? \PHP_INT_MAX : $sum + $term;
        }

        return $sum;
    }

    /**
     * A product of counts, none below zero, that stops at the largest integer.
     */
    private static function times(int $left, int $right): int
    {
        return 0 !== $left && $right > intdiv(\PHP_INT_MAX, $left) ? \PHP_INT_MAX : $left * $right;
    }

    /**
     * Checks the sides of a die known before the roll, and counts no die.
     */
    private function sides(object $die): int
    {
        if ($die instanceof StandardDie) {
            $this->checkSides($die->sides);
        } elseif ($die instanceof RangeDie && $die->to - $die->from >= $this->sides) {
            throw LimitExceeded::of('sides of a die', $this->sides, $die->to - $die->from + 1);
        }

        return 0;
    }

    private function limit(?Explosion $explosion): int
    {
        return $explosion?->limit instanceof Node ? $this->count($explosion->limit) : 0;
    }
}
