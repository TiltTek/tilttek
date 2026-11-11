<?php

declare(strict_types=1);

namespace Tilttek\Dice\Dialect;

use Override;
use Tilttek\Dice\Die\NumericDie;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Constant;
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
 * Foundry Virtual Tabletop's dice: `/r`, a reroll once written `r` and repeated `rr`, explosions `x` and once `xo`,
 * floors and ceilings `min` and `max`, `kh kl dh dl`, successes counted by `cs` and failures deducted by `df`, pools
 * of values `{…}`, and comparisons in their plain sense, each written with its sign. A division is rounded by
 * `floor()`, `ceil()` or `round()`; a power is `**`; `abs()`, `min()` and `max()` are written.
 */
final class FoundryVtt extends Dialect
{
    #[Override]
    public function name(): string
    {
        return 'foundry';
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
            'a transformation' => [] !== $modifiers->transforms,
            'a filter' => $modifiers->filter instanceof Condition,
            'a count of occurrences' => $modifiers->occurrences instanceof Occurrences,
            'a count of groups' => null !== $modifiers->groups,
            'a critical mark' => $modifiers->criticalSuccess instanceof Critical || $modifiers->criticalFailure instanceof Critical,
            'a look token' => [] !== $modifiers->looks,
            'a sort' => $modifiers->sort instanceof Sort,
        ]);

        return ($modifiers->reroll instanceof Reroll ? $this->reroll($modifiers->reroll, $node) : '')
            .($modifiers->explosion instanceof Explosion ? $this->explosion($modifiers->explosion, $node) : '')
            .(null === $modifiers->minimum ? '' : 'min'.$modifiers->minimum)
            .(null === $modifiers->maximum ? '' : 'max'.$modifiers->maximum)
            .$this->counted($modifiers, $node);
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

        return '{'.implode(',', $list->values).'}'.$this->counted($modifiers, $list);
    }

    #[Override]
    protected function call(FunctionCall $call): string
    {
        return $this->function($call);
    }

    #[Override]
    protected function division(Operation $operation, string $left, string $right, MathFunction $rounding): array
    {
        if (MathFunction::Round === $rounding && (!$this->nonNegative($operation->left) || !$this->nonNegative($operation->right))) {
            return [$this->unsupported($operation, 'round() rounds the negative halves up'), self::ATOM];
        }

        return [$rounding->value.'('.$left.'/'.$right.')', self::ATOM];
    }

    /**
     * The selection, the successes and the failures.
     */
    private function counted(Modifiers $modifiers, Node $node): string
    {
        $success = $modifiers->success instanceof Condition ? $this->plainSuccess($modifiers->success) : null;

        return ($modifiers->selection instanceof Selection ? $modifiers->selection->mode->value.$modifiers->selection->count : '')
            .match (true) {
                !$modifiers->success instanceof Condition => '',
                $success instanceof Comparison => 'cs'.$this->target($success),
                default => $this->unsupported($node, 'successes compare each final value with one threshold'),
            }
        .($modifiers->failure instanceof Comparison ? 'df'.$this->target($modifiers->failure) : '');
    }

    private function reroll(Reroll $reroll, Node $node): string
    {
        $on = $this->plain($reroll->on);

        return $on instanceof Comparison ? (Repetition::Once === $reroll->repetition ? 'r' : 'rr').$this->target($on) : $this->unsupported($node, 'a reroll compares with one threshold');
    }

    private function explosion(Explosion $explosion, Node $node): string
    {
        $on = $this->plain($explosion->on);
        $once = $explosion->limit instanceof Constant && 1 === $explosion->limit->value;

        return match (true) {
            $explosion->compound || $explosion->penetrating => $this->unsupported($node, 'an explosion neither compounds nor penetrates'),
            $explosion->limit instanceof Node && !$once => $this->unsupported($node, 'an explosion is limited to once at most'),
            $explosion->on instanceof Validator && !$on instanceof Comparison => $this->unsupported($node, 'an explosion compares with one threshold'),
            default => ($once ? 'xo' : 'x').($on instanceof Comparison ? $this->target($on) : ''),
        };
    }

    private function target(Comparison $comparison): string
    {
        return $comparison->comparator->value.$comparison->value;
    }
}
