<?php

declare(strict_types=1);

namespace Tilttek\Dice\Dialect;

use Override;
use Tilttek\Dice\Die\NumericDie;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Critical;
use Tilttek\Dice\Expression\Explosion;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Occurrences;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Repetition;
use Tilttek\Dice\Expression\Reroll;
use Tilttek\Dice\Expression\Selection;
use Tilttek\Dice\Expression\Sort;
use Tilttek\Dice\Expression\Validator;
use Tilttek\Dice\Expression\ValueList;

/**
 * Roll20's dice: `/r`, rerolls `r` and once `ro`, explosions `!`, compounding `!!` and penetrating `!p`, `kh kl dh dl`,
 * successes and failures `f`, critical marks `cs` and `cf`, sorting `sa` and `sd`, groups of values `{…}`, and
 * comparison points where `>` and `<` include the threshold, so `>5` of the library's notation is `>6` here. A
 * division is rounded by `floor()`, `ceil()` or `round()`; a power is `**`; `abs()` is written.
 */
final class Roll20 extends Dialect
{
    #[Override]
    public function name(): string
    {
        return 'roll20';
    }

    #[Override]
    protected function command(): string
    {
        return '/r ';
    }

    #[Override]
    protected function modifiers(Modifiers $modifiers, Node $node, ?NumericDie $die): string
    {
        $this->unwritten($node, [
            'a unique reroll' => $modifiers->unique instanceof Repetition,
            'an explosion of dice all the same' => $modifiers->explodeAllSame,
            'a floor' => null !== $modifiers->minimum,
            'a ceiling' => null !== $modifiers->maximum,
            'a transformation' => [] !== $modifiers->transforms,
            'a filter' => $modifiers->filter instanceof Condition,
            'a count of occurrences' => $modifiers->occurrences instanceof Occurrences,
            'a count of groups' => null !== $modifiers->groups,
            'a look token' => [] !== $modifiers->looks,
        ]);

        return ($modifiers->reroll instanceof Reroll ? $this->reroll($modifiers->reroll, $node) : '')
            .($modifiers->explosion instanceof Explosion ? $this->explosion($modifiers->explosion, $node) : '')
            .($modifiers->selection instanceof Selection ? $modifiers->selection->mode->value.$modifiers->selection->count : '')
            .($modifiers->success instanceof Condition ? $this->success($modifiers->success, $node) : '')
            .($modifiers->failure instanceof Comparison ? 'f'.$this->point($modifiers->failure, true) : '')
            .$this->critical('cs', $modifiers->criticalSuccess, $node, $die, true)
            .$this->critical('cf', $modifiers->criticalFailure, $node, $die, false)
            .($modifiers->sort instanceof Sort ? $modifiers->sort->value : '');
    }

    #[Override]
    protected function valueList(ValueList $list): string
    {
        $modifiers = $list->modifiers;
        $this->unwritten($list, [
            'a floor' => null !== $modifiers->minimum,
            'a ceiling' => null !== $modifiers->maximum,
            'a transformation' => [] !== $modifiers->transforms,
            'a filter' => $modifiers->filter instanceof Condition,
            'a count of occurrences' => $modifiers->occurrences instanceof Occurrences,
            'a count of groups' => null !== $modifiers->groups,
            'a critical mark' => $modifiers->criticalSuccess instanceof Critical || $modifiers->criticalFailure instanceof Critical,
            'a look token' => [] !== $modifiers->looks,
            'a sort' => $modifiers->sort instanceof Sort,
        ]);

        return '{'.implode(',', $list->values).'}'
            .($modifiers->selection instanceof Selection ? $modifiers->selection->mode->value.$modifiers->selection->count : '')
            .($modifiers->success instanceof Condition ? $this->success($modifiers->success, $list) : '')
            .($modifiers->failure instanceof Comparison ? 'f'.$this->point($modifiers->failure, true) : '');
    }

    #[Override]
    protected function call(FunctionCall $call): string
    {
        return $call->function->isVariadic() ? parent::call($call) : $this->function($call);
    }

    #[Override]
    protected function division(Operation $operation, string $left, string $right, MathFunction $rounding): array
    {
        if (MathFunction::Round === $rounding && (!$this->nonNegative($operation->left) || !$this->nonNegative($operation->right))) {
            return [$this->unsupported($operation, 'round() rounds the negative halves up'), self::ATOM];
        }

        return [$rounding->value.'('.$left.'/'.$right.')', self::ATOM];
    }

    private function reroll(Reroll $reroll, Node $node): string
    {
        $on = $this->plain($reroll->on);

        return $on instanceof Comparison ? (Repetition::Once === $reroll->repetition ? 'ro' : 'r').$this->point($on, true) : $this->unsupported($node, 'a reroll compares with one point');
    }

    private function explosion(Explosion $explosion, Node $node): string
    {
        $on = $this->plain($explosion->on);

        return match (true) {
            $explosion->limit instanceof Node => $this->unsupported($node, 'a limit of explosions is not written'),
            $explosion->compound && $explosion->penetrating => $this->unsupported($node, 'a penetrating explosion does not compound'),
            $explosion->on instanceof Validator && !$on instanceof Comparison => $this->unsupported($node, 'an explosion compares with one point'),
            default => ($explosion->compound ? '!!' : ($explosion->penetrating ? '!p' : '!')).($on instanceof Comparison ? $this->point($on, true) : ''),
        };
    }

    private function success(Condition $success, Node $node): string
    {
        $comparison = $this->plainSuccess($success);

        return $comparison instanceof Comparison ? $this->point($comparison, false) : $this->unsupported($node, 'successes compare each final value with one point');
    }

    private function critical(string $code, ?Critical $critical, Node $node, ?NumericDie $die, bool $highest): string
    {
        if (!$critical instanceof Critical) {
            return '';
        }

        $on = $this->bound($critical->on, $die, $highest);

        return $on instanceof Comparison ? $code.$this->point($on, true) : $this->unsupported($node, 'a critical mark compares with one point');
    }

    /**
     * A comparison point of a plain comparison: `>` and `<` include their threshold; a face alone may be written bare.
     */
    private function point(Comparison $comparison, bool $bare): string
    {
        return match ($comparison->comparator) {
            Comparator::GreaterOrEqual => '>'.$comparison->value,
            Comparator::Greater => '>'.($comparison->value + 1),
            Comparator::LessOrEqual => '<'.$comparison->value,
            Comparator::Less => '<'.($comparison->value - 1),
            default => ($bare ? '' : '=').$comparison->value,
        };
    }
}
