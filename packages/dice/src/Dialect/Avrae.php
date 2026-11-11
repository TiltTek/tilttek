<?php

declare(strict_types=1);

namespace Tilttek\Dice\Dialect;

use Override;
use RoundingMode;
use Tilttek\Dice\Die\FudgeDie;
use Tilttek\Dice\Die\NumericDie;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Critical;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Explosion;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Occurrences;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Operator;
use Tilttek\Dice\Expression\Repetition;
use Tilttek\Dice\Expression\Reroll;
use Tilttek\Dice\Expression\Selection;
use Tilttek\Dice\Expression\SelectionMode;
use Tilttek\Dice\Expression\Sort;
use Tilttek\Dice\Expression\Validator;
use Tilttek\Dice\Expression\ValueList;

/**
 * Avrae's dice, on Discord: `!r`, rerolls once `ro` and repeated `rr`, explosions `e` with a selector — the highest
 * face written out —, floors and ceilings `mi` and `ma`, keep `k` and drop `p` with `h` and `l`, sets of values `(…)`,
 * selectors `>` and `<` that exclude their threshold, the floor division `//` and the remainder `%`, which has the sign
 * of the divisor as the library's. `floor()`, `ceil()` and `round()` write their argument alone — it is an integer —,
 * its divisions rounded down under `floor()` and reported under the others. Avrae counts no successes, has no Fudge
 * dice and no other function: they are reported.
 */
final class Avrae extends Dialect
{
    #[Override]
    public function name(): string
    {
        return 'avrae';
    }

    #[Override]
    protected function command(): string
    {
        return '!r ';
    }

    #[Override]
    protected function modifiers(Modifiers $modifiers, Node $node, ?NumericDie $die): string
    {
        $this->unwritten($node, [
            'a floor of a set' => !$die instanceof NumericDie && null !== $modifiers->minimum,
            'a ceiling of a set' => !$die instanceof NumericDie && null !== $modifiers->maximum,
            'a unique reroll' => $modifiers->unique instanceof Repetition,
            'an explosion of dice all the same' => $modifiers->explodeAllSame,
            'a transformation' => [] !== $modifiers->transforms,
            'a filter' => $modifiers->filter instanceof Condition,
            'a count of successes' => $modifiers->success instanceof Condition,
            'a count of occurrences' => $modifiers->occurrences instanceof Occurrences,
            'a count of groups' => null !== $modifiers->groups,
            'a critical mark' => $modifiers->criticalSuccess instanceof Critical || $modifiers->criticalFailure instanceof Critical,
            'a look token' => [] !== $modifiers->looks,
            'a sort' => $modifiers->sort instanceof Sort,
        ]);

        return ($modifiers->reroll instanceof Reroll ? $this->reroll($modifiers->reroll, $node) : '')
            .($modifiers->explosion instanceof Explosion ? $this->explosion($modifiers->explosion, $node, $die) : '')
            .(null === $modifiers->minimum || !$die instanceof NumericDie ? '' : 'mi'.$modifiers->minimum)
            .(null === $modifiers->maximum || !$die instanceof NumericDie ? '' : 'ma'.$modifiers->maximum)
            .($modifiers->selection instanceof Selection ? $this->selection($modifiers->selection) : '');
    }

    #[Override]
    protected function fudge(Dice $dice, FudgeDie $die): string
    {
        return $this->unsupported($dice, 'Avrae has no Fudge dice', 'F');
    }

    #[Override]
    protected function valueList(ValueList $list): string
    {
        return '('.implode(', ', $list->values).')'.$this->modifiers($list->modifiers, $list, null);
    }

    #[Override]
    protected function call(FunctionCall $call): string
    {
        return $call->function->rounding() instanceof RoundingMode ? '('.$this->rounded($call)[0].')' : parent::call($call);
    }

    #[Override]
    protected function division(Operation $operation, string $left, string $right, MathFunction $rounding): array
    {
        return MathFunction::Floor === $rounding
            ? [$left.'//'.$right, Operator::Divide->precedence()]
            : [$this->unsupported($operation, \sprintf('a division inside %s() is not written', $rounding->value)), self::ATOM];
    }

    #[Override]
    protected function modulo(Operation $operation, string $left, string $right): array
    {
        return [$left.'%'.$right, Operator::Modulo->precedence()];
    }

    #[Override]
    protected function power(Operation $operation, string $left, string $right): array
    {
        return [$this->unsupported($operation, 'a power is not written'), self::ATOM];
    }

    private function reroll(Reroll $reroll, Node $node): string
    {
        $selector = $this->selector($reroll->on);

        return null === $selector ? $this->unsupported($node, 'a reroll selects with one comparison') : (Repetition::Once === $reroll->repetition ? 'ro' : 'rr').$selector;
    }

    private function explosion(Explosion $explosion, Node $node, ?NumericDie $die): string
    {
        $on = $this->bound($explosion->on, $die, true);
        $selector = $on instanceof Comparison ? $this->selector($on) : null;

        return match (true) {
            $explosion->compound || $explosion->penetrating => $this->unsupported($node, 'an explosion neither compounds nor penetrates'),
            $explosion->limit instanceof Node => $this->unsupported($node, 'a limit of explosions is not written'),
            null === $selector => $this->unsupported($node, 'an explosion selects with one comparison'),
            default => 'e'.$selector,
        };
    }

    private function selection(Selection $selection): string
    {
        return match ($selection->mode) {
            SelectionMode::KeepHighest => 'kh',
            SelectionMode::KeepLowest => 'kl',
            SelectionMode::DropHighest => 'ph',
            SelectionMode::DropLowest => 'pl',
        }.$selection->count;
    }

    /**
     * A selector: a face alone, or `>` and `<`, which exclude their threshold; null for any other validator.
     */
    private function selector(Validator $validator): ?string
    {
        $comparison = $this->plain($validator);

        return match ($comparison?->comparator) {
            Comparator::Equal => (string) $comparison->value,
            Comparator::Greater => '>'.$comparison->value,
            Comparator::GreaterOrEqual => '>'.($comparison->value - 1),
            Comparator::Less => '<'.$comparison->value,
            Comparator::LessOrEqual => '<'.($comparison->value + 1),
            default => null,
        };
    }
}
