<?php

declare(strict_types=1);

namespace Tilttek\Dice\Dialect;

use Override;
use Tilttek\Dice\Die\CustomDie;
use Tilttek\Dice\Die\FudgeDie;
use Tilttek\Dice\Die\NumericDie;
use Tilttek\Dice\Die\RangeDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Expression\Choice;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\ComputedDice;
use Tilttek\Dice\Expression\Condition;
use Tilttek\Dice\Expression\Conditional;
use Tilttek\Dice\Expression\Constant;
use Tilttek\Dice\Expression\Critical;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Explosion;
use Tilttek\Dice\Expression\FunctionCall;
use Tilttek\Dice\Expression\Group;
use Tilttek\Dice\Expression\InstructionDice;
use Tilttek\Dice\Expression\Look;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Occurrences;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Operator;
use Tilttek\Dice\Expression\PreviousDice;
use Tilttek\Dice\Expression\Reference;
use Tilttek\Dice\Expression\Repetition;
use Tilttek\Dice\Expression\Reroll;
use Tilttek\Dice\Expression\Scope;
use Tilttek\Dice\Expression\Selection;
use Tilttek\Dice\Expression\SelectionMode;
use Tilttek\Dice\Expression\Sentence;
use Tilttek\Dice\Expression\Sort;
use Tilttek\Dice\Expression\SwitchCase;
use Tilttek\Dice\Expression\TextList;
use Tilttek\Dice\Expression\Transform;
use Tilttek\Dice\Expression\Validator;
use Tilttek\Dice\Expression\ValueList;
use Tilttek\Dice\Notation\NotationWriter;

/**
 * The notation of Rolisteam's DiceParser: `!`, every instruction of a pool in one command separated by `;`, rerolls
 * `r[…]` once and `R[…]` repeated, explosions `e[…]` and their limit `e(3)[…]`, rerolls that add `a[…]`, unique dice
 * `u`, dice all the same `t`, transformations `T[…]{…}[…]{…}`, filters `f[…]`, keep `k` and `kl`, counts `c[…]`, occurrences
 * `o2,7`, groups `g`, paint `p[…]` from look tokens, sorts `s` and `sl`, validators between brackets with their scope
 * written first — `*` all, `.` any, `:` the total —, dice on a range `d[a..b]`, lists of values, `@`, `$1`, `@1`, groups
 * of expressions, text lists, sentences, `i[…]{…}{…}`, `S[…]{…}`, `repeat()`, `floor()`, `ceil()`, `round()`, `abs()`,
 * `min()`, `max()`, `%` and `^`. DiceParser computes in real numbers: a division is written inside the function that
 * rounds it.
 */
final class RolisteamDiceParser extends Dialect
{
    #[Override]
    public function name(): string
    {
        return 'diceparser';
    }

    #[Override]
    protected function command(): string
    {
        return '!';
    }

    #[Override]
    protected function separator(): string
    {
        return ';';
    }

    #[Override]
    protected function modifiers(Modifiers $modifiers, Node $node, ?NumericDie $die): string
    {
        $this->unwritten($node, [
            'a unique reroll once' => Repetition::Once === $modifiers->unique,
            'a floor' => null !== $modifiers->minimum,
            'a ceiling' => null !== $modifiers->maximum,
            'a count of failures' => $modifiers->failure instanceof Comparison,
            'a critical mark' => $modifiers->criticalSuccess instanceof Critical || $modifiers->criticalFailure instanceof Critical,
        ]);

        return ($modifiers->reroll instanceof Reroll ? $this->reroll($modifiers->reroll, $node) : '')
            .(Repetition::Repeated === $modifiers->unique ? 'u' : '')
            .($modifiers->explosion instanceof Explosion ? $this->explosion($modifiers->explosion, $node, $die) : '')
            .($modifiers->explodeAllSame ? 't' : '')
            .([] === $modifiers->transforms ? '' : 'T'.implode('', array_map(fn (Transform $transform): string => '['.$this->condition($transform->when, $node).']{'.$transform->value.'}', $modifiers->transforms)))
            .($modifiers->filter instanceof Condition ? 'f['.$this->condition($modifiers->filter, $node).']' : '')
            .($modifiers->selection instanceof Selection ? $this->selection($modifiers->selection, $node) : '')
            .($modifiers->success instanceof Condition ? 'c['.$this->condition($modifiers->success, $node).']' : '')
            .($modifiers->occurrences instanceof Occurrences ? $this->occurrences($modifiers->occurrences, $node) : '')
            .(null === $modifiers->groups ? '' : 'g'.$modifiers->groups)
            .([] === $modifiers->looks ? '' : 'p['.implode(',', array_map(static fn (Look $look): string => (null === $look->count ? '' : $look->count.':').$look->token, $modifiers->looks)).']')
            .match ($modifiers->sort) {
                Sort::Descending => 's',
                Sort::Ascending => 'sl',
                null => '',
            };
    }

    /**
     * Fudge dice, written as dice from −1 to 1; weighted ones are reported.
     */
    #[Override]
    protected function fudge(Dice $dice, FudgeDie $die): string
    {
        return 1 === $die->variant ? parent::fudge($dice, $die) : '[-1..1]';
    }

    #[Override]
    protected function rangeDie(Dice $dice, RangeDie $die): string
    {
        return '['.$die->from.'..'.$die->to.']';
    }

    #[Override]
    protected function computedDice(ComputedDice $dice): string
    {
        $die = $dice->die;
        $sides = match (true) {
            $die instanceof Node => '('.$this->node($die)[0].')',
            $die instanceof StandardDie => (string) $die->sides,
            $die instanceof RangeDie => '['.$die->from.'..'.$die->to.']',
            $die instanceof FudgeDie && 1 !== $die->variant => '[-1..1]',
            default => $this->unsupported($dice, $die instanceof CustomDie ? 'custom dice are declared by each site' : 'weighted Fudge dice are not written'),
        };

        return $this->count($dice->count).'d'.$sides.$this->modifiers($dice->modifiers, $dice, $die instanceof NumericDie ? $die : null);
    }

    #[Override]
    protected function valueList(ValueList $list): string
    {
        return '['.implode(',', $list->values).']'.$this->modifiers($list->modifiers, $list, null);
    }

    #[Override]
    protected function previousDice(PreviousDice $previous): string
    {
        return '@'.$this->modifiers($previous->modifiers, $previous, null);
    }

    #[Override]
    protected function call(FunctionCall $call): string
    {
        return MathFunction::Repeat === $call->function ? 'repeat('.implode(', ', $this->arguments($call)).')' : $this->function($call);
    }

    #[Override]
    protected function division(Operation $operation, string $left, string $right, MathFunction $rounding): array
    {
        if (MathFunction::Round === $rounding && (!$this->nonNegative($operation->left) || !$this->nonNegative($operation->right))) {
            return [$this->unsupported($operation, 'round() may round the negative halves otherwise'), self::ATOM];
        }

        return [$rounding->value.'('.$left.'/'.$right.')', self::ATOM];
    }

    /**
     * A remainder, written when neither operand can be negative, since DiceParser's may keep the sign of the dividend.
     */
    #[Override]
    protected function modulo(Operation $operation, string $left, string $right): array
    {
        return $this->nonNegative($operation->left) && $this->nonNegative($operation->right)
            ? [$left.'%'.$right, Operator::Modulo->precedence()]
            : [$this->unsupported($operation, 'a remainder of a negative value may have the sign of the dividend'), self::ATOM];
    }

    /**
     * A power, `^`, between parentheses, when its exponent cannot be negative.
     */
    #[Override]
    protected function power(Operation $operation, string $left, string $right): array
    {
        return [$this->nonNegative($operation->right) ? '('.$left.'^'.$right.')' : $this->unsupported($operation, 'a power whose exponent may be negative is not written'), self::ATOM];
    }

    #[Override]
    protected function reference(Reference $reference): string
    {
        return '$'.$reference->instruction.(null === $reference->index ? '' : '['.$reference->index.']');
    }

    #[Override]
    protected function instructionDice(InstructionDice $dice): string
    {
        if ($dice->merge || \count($dice->instructions) > 1) {
            return $this->unsupported($dice, 'the dice of several instructions are not bound or merged');
        }

        return '@'.$dice->instructions[0].$this->modifiers($dice->modifiers, $dice, null);
    }

    #[Override]
    protected function group(Group $group): string
    {
        return '{'.implode(',', array_map(fn (Node $expression): string => $this->node($expression)[0], $group->expressions)).'}'.$this->modifiers($group->modifiers, $group, null);
    }

    #[Override]
    protected function textList(TextList $list): string
    {
        $writer = new NotationWriter();

        return $this->count($list->count).'L'.($list->unique ? 'u' : '').($list->joined ? 'n' : '').'['.implode(',', array_map($writer->writeItem(...), $list->items)).']';
    }

    #[Override]
    protected function sentence(Sentence $sentence): string
    {
        return new NotationWriter()->writeNode($sentence);
    }

    #[Override]
    protected function conditional(Conditional $conditional, string $subject): string
    {
        return $subject.'i['.$this->condition($conditional->condition, $conditional).']{'.$this->node($conditional->then)[0].'}'
            .($conditional->else instanceof Node ? '{'.$this->node($conditional->else)[0].'}' : '');
    }

    #[Override]
    protected function choice(Choice $choice, string $subject): string
    {
        return $subject.'S'.($choice->exclusive ? '^' : '')
            .implode('', array_map(fn (SwitchCase $case): string => '['.$this->condition($case->when, $choice).']{'.$this->node($case->then)[0].'}', $choice->cases))
            .($choice->default instanceof Node ? '{'.$this->node($choice->default)[0].'}' : '');
    }

    /**
     * A count written as a number when it is one, between parentheses otherwise.
     */
    private function count(Node $count): string
    {
        return $count instanceof Constant && $count->value >= 0 ? (string) $count->value : '('.$this->node($count)[0].')';
    }

    private function reroll(Reroll $reroll, Node $node): string
    {
        return (Repetition::Once === $reroll->repetition ? 'r' : 'R').'['.$this->condition(new Condition($reroll->on), $node).']';
    }

    /**
     * An explosion, on the highest face when it names none; a compound explosion only once, as a reroll that adds.
     */
    private function explosion(Explosion $explosion, Node $node, ?NumericDie $die): string
    {
        $on = $explosion->on ?? ($die instanceof NumericDie ? new Comparison(Comparator::Equal, $die->highest()) : null);
        $once = $explosion->limit instanceof Constant && 1 === $explosion->limit->value;

        return match (true) {
            !$on instanceof Validator => $this->unsupported($node, 'an explosion names the face it explodes on when the sides are rolled first'),
            $explosion->penetrating => $this->unsupported($node, 'a penetrating explosion is not written'),
            $explosion->compound && !$once => $this->unsupported($node, 'a compound explosion is written once at most'),
            $explosion->compound => 'a['.$this->condition(new Condition($on), $node).']',
            default => 'e'.($explosion->limit instanceof Node ? '('.$this->node($explosion->limit)[0].')' : '').'['.$this->condition(new Condition($on), $node).']',
        };
    }

    /**
     * Keep the highest `k` or the lowest `kl`; dropping dice keeps the others, when the number of dice is known.
     */
    private function selection(Selection $selection, Node $node): string
    {
        if ($selection->mode->keeps()) {
            return (SelectionMode::KeepHighest === $selection->mode ? 'k' : 'kl').$selection->count;
        }

        $dice = $this->known($node);

        if (null === $dice) {
            return $this->unsupported($node, 'dropped dice are written as the dice kept, for a known number of dice');
        }

        return (SelectionMode::DropLowest === $selection->mode ? 'k' : 'kl').($dice - $selection->count);
    }

    /**
     * The number of dice a selection chooses among, when the expression shows it: no explosion that adds dice, no
     * filter before it.
     */
    private function known(Node $node): ?int
    {
        [$modifiers, $count] = match (true) {
            $node instanceof Dice => [$node->modifiers, $node->count],
            $node instanceof ValueList => [$node->modifiers, \count($node->values)],
            $node instanceof Group => [$node->modifiers, \count($node->expressions)],
            default => [new Modifiers(), null],
        };
        $adds = $modifiers->explosion instanceof Explosion && !$modifiers->explosion->compound;

        return $adds || $modifiers->explodeAllSame || $modifiers->filter instanceof Condition ? null : $count;
    }

    /**
     * `o2`, or `o2,7` for sets of a value of 7 or more.
     */
    private function occurrences(Occurrences $occurrences, Node $node): string
    {
        $height = $occurrences->height;

        return match (true) {
            !$height instanceof Validator => 'o'.$occurrences->width,
            $height instanceof Comparison && Comparator::GreaterOrEqual === $height->comparator => 'o'.$occurrences->width.','.$height->value,
            $height instanceof Comparison && Comparator::Greater === $height->comparator => 'o'.$occurrences->width.','.($height->value + 1),
            default => $this->unsupported($node, 'occurrences are judged by their lowest value'),
        };
    }

    /**
     * A condition's scope, written first — nothing for each final value —, and its validator.
     */
    private function condition(Condition $condition, Node $node): string
    {
        $scope = match ($condition->scope) {
            Scope::Value => '',
            Scope::All => '*',
            Scope::Any => '.',
            Scope::Total => ':',
            Scope::Die => $this->unsupported($node, 'a condition on each die as it fell is not written'),
        };

        return $scope.new NotationWriter()->writeValidator($condition->validator);
    }
}
