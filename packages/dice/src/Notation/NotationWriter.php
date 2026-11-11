<?php

declare(strict_types=1);

namespace Tilttek\Dice\Notation;

use Override;
use Tilttek\Dice\Die\CustomDie;
use Tilttek\Dice\Die\DieType;
use Tilttek\Dice\Die\FudgeDie;
use Tilttek\Dice\Die\RangeDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Expression\Choice;
use Tilttek\Dice\Expression\Comparator;
use Tilttek\Dice\Expression\Comparison;
use Tilttek\Dice\Expression\Composite;
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
use Tilttek\Dice\Expression\ListItem;
use Tilttek\Dice\Expression\Look;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Modulo;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Occurrences;
use Tilttek\Dice\Expression\Placeholder;
use Tilttek\Dice\Expression\PlaceholderKind;
use Tilttek\Dice\Expression\Pool;
use Tilttek\Dice\Expression\PreviousDice;
use Tilttek\Dice\Expression\Range;
use Tilttek\Dice\Expression\Reference;
use Tilttek\Dice\Expression\Repetition;
use Tilttek\Dice\Expression\Reroll;
use Tilttek\Dice\Expression\Scope;
use Tilttek\Dice\Expression\Selection;
use Tilttek\Dice\Expression\Sentence;
use Tilttek\Dice\Expression\Sort;
use Tilttek\Dice\Expression\SwitchCase;
use Tilttek\Dice\Expression\TextList;
use Tilttek\Dice\Expression\Transform;
use Tilttek\Dice\Expression\Validator;
use Tilttek\Dice\Expression\ValueList;

/**
 * Writes an expression in the library's notation, with the letters of a language: every count written, the modifiers
 * in the order they apply, the instructions of a pool separated by a comma and a space, texts between double quotes
 * with their escapes, and the items of a text list as written unless they need quotes. With the default notation,
 * this is the canonical form; comments are not written.
 */
final class NotationWriter extends ExpressionWriter
{
    public function __construct(
        private readonly Notation $notation = new Notation(),
    ) {
    }

    public function write(Pool $pool): string
    {
        return implode(', ', array_map($this->writeNode(...), $pool->expressions));
    }

    public function writeNode(Node $node): string
    {
        return $this->node($node)[0];
    }

    /**
     * A validator alone, as written between brackets.
     */
    public function writeValidator(Validator $validator): string
    {
        if (!$validator instanceof Composite) {
            return $this->atom($validator);
        }

        $precedence = $validator->junction->precedence();

        return $this->operand($validator->left, $precedence).$validator->junction->value.$this->operand($validator->right, $precedence + 1);
    }

    #[Override]
    protected function dice(Dice $dice): string
    {
        return $dice->count.$this->notation->die.$this->die($dice->die).$this->modifiers($dice->modifiers);
    }

    #[Override]
    protected function computedDice(ComputedDice $dice): string
    {
        return $this->bound($dice->count).$this->notation->die.($dice->die instanceof Node ? '('.$this->writeNode($dice->die).')' : $this->die($dice->die)).$this->modifiers($dice->modifiers);
    }

    #[Override]
    protected function valueList(ValueList $list): string
    {
        return '['.implode(',', $list->values).']'.$this->modifiers($list->modifiers);
    }

    #[Override]
    protected function previousDice(PreviousDice $previous): string
    {
        return '@'.$this->modifiers($previous->modifiers);
    }

    #[Override]
    protected function call(FunctionCall $call): string
    {
        return $call->function->value.'('.implode(', ', $this->arguments($call)).')';
    }

    #[Override]
    protected function reference(Reference $reference): string
    {
        return '$'.$reference->instruction.(null === $reference->index ? '' : '['.$reference->index.']');
    }

    #[Override]
    protected function instructionDice(InstructionDice $dice): string
    {
        $head = !$dice->merge && 1 === \count($dice->instructions) ? '@'.$dice->instructions[0] : ($dice->merge ? 'm' : 'b').'['.implode(',', $dice->instructions).']';

        return $head.$this->modifiers($dice->modifiers);
    }

    #[Override]
    protected function group(Group $group): string
    {
        return '{'.implode(', ', array_map($this->writeNode(...), $group->expressions)).'}'.$this->modifiers($group->modifiers);
    }

    #[Override]
    protected function textList(TextList $list): string
    {
        return $this->bound($list->count).'L'.($list->unique ? 'u' : '').($list->joined ? 'n' : '').'['.implode(',', array_map($this->writeItem(...), $list->items)).']';
    }

    #[Override]
    protected function sentence(Sentence $sentence): string
    {
        $text = '';
        $previous = null;

        foreach ($sentence->parts as $part) {
            $text .= $part instanceof Placeholder ? $this->placeholder($part) : $this->escape($part, true, $previous);
            $previous = $part;
        }

        return '"'.$text.'"';
    }

    #[Override]
    protected function conditional(Conditional $conditional, string $subject): string
    {
        return $subject.'i['.$this->condition($conditional->condition).']'.$this->branch($conditional->then).($conditional->else instanceof Node ? $this->branch($conditional->else) : '');
    }

    #[Override]
    protected function choice(Choice $choice, string $subject): string
    {
        $cases = array_map(fn (SwitchCase $case): string => '['.$this->condition($case->when).']'.$this->branch($case->then), $choice->cases);

        return $subject.'S'.($choice->exclusive ? '^' : '').implode('', $cases).($choice->default instanceof Node ? $this->branch($choice->default) : '');
    }

    private function branch(Node $node): string
    {
        return '{'.$this->writeNode($node).'}';
    }

    private function placeholder(Placeholder $placeholder): string
    {
        return $placeholder->kind->namesInstruction()
            ? $placeholder->kind->value.$placeholder->instruction.(null === $placeholder->index ? '' : '['.$placeholder->index.']')
            : $placeholder->kind->value;
    }

    /**
     * An item of a text list, written as it is unless a sign of the notation, a range, a space at either end or a
     * character that is not printed asks for quotes.
     */
    public function writeItem(ListItem $item): string
    {
        $bare = $item->text === trim($item->text) && !str_contains($item->text, '..') && !str_contains($item->text, '//');

        // Byte by byte: every character the check looks for is in ASCII, and no byte of another character is one.
        foreach (str_split($item->text) as $character) {
            $bare = $bare && !str_contains(',[]"\\#{}', $character) && !ctype_cntrl($character);
        }

        return ($item->range instanceof Range ? $item->range->from.'..'.$item->range->to.':' : '')
            .($bare ? $item->text : '"'.$this->escape($item->text, false, null).'"')
            .(1 === $item->weight ? '' : '['.$item->weight.']');
    }

    /**
     * A text between double quotes: a backslash before a backslash or a quote, `\n` for a line break, and — in a
     * sentence — before a sign that would start a placeholder, and before a digit or a bracket that would lengthen the
     * placeholder just before.
     */
    private function escape(string $text, bool $sentence, string|Placeholder|null $previous): string
    {
        // Byte by byte, as above: the signs escaped and the digits are in ASCII.
        $characters = str_split($text);
        $escaped = '';

        foreach ($characters as $offset => $character) {
            $lengthens = 0 === $offset && $previous instanceof Placeholder && (ctype_digit($character) || ('[' === $character && PlaceholderKind::Result === $previous->kind && null === $previous->index));
            $starts = \in_array($character, ['%', '$', '@'], true) && ctype_digit($characters[$offset + 1] ?? '');
            $escaped .= match (true) {
                "\n" === $character => '\\n',
                '\\' === $character, '"' === $character, $sentence && ($starts || $lengthens) => '\\'.$character,
                default => $character,
            };
        }

        return $escaped;
    }

    private function die(DieType $die): string
    {
        return match (true) {
            $die instanceof StandardDie => (string) $die->sides,
            $die instanceof CustomDie => '{'.$die->name.'}',
            $die instanceof FudgeDie => 1 === $die->variant ? 'F.1' : 'F',
            $die instanceof RangeDie => '['.$die->from.'..'.$die->to.']',
            default => throw InvalidDefinition::because(\sprintf('%s is not a die the library writes.', $die::class)),
        };
    }

    /**
     * A count written as a number when it is one, between parentheses otherwise.
     */
    private function bound(Node $node): string
    {
        return $node instanceof Constant && $node->value >= 0 ? (string) $node->value : '('.$this->writeNode($node).')';
    }

    private function modifiers(Modifiers $modifiers): string
    {
        return implode('', [
            $modifiers->reroll instanceof Reroll ? (Repetition::Once === $modifiers->reroll->repetition ? 'ro' : 'r').$this->threshold($modifiers->reroll->on) : '',
            match ($modifiers->unique) {
                Repetition::Repeated => 'u',
                Repetition::Once => 'uo',
                null => '',
            },
            $modifiers->explosion instanceof Explosion ? $this->explosion($modifiers->explosion) : '',
            $modifiers->explodeAllSame ? 't' : '',
            null === $modifiers->minimum ? '' : 'min'.$modifiers->minimum,
            null === $modifiers->maximum ? '' : 'max'.$modifiers->maximum,
            [] === $modifiers->transforms ? '' : 't['.implode(',', array_map(fn (Transform $transform): string => $this->condition($transform->when).':'.$transform->value, $modifiers->transforms)).']',
            $modifiers->filter instanceof Condition ? 'f['.$this->condition($modifiers->filter).']' : '',
            $modifiers->selection instanceof Selection ? $modifiers->selection->mode->value.$modifiers->selection->count : '',
            $modifiers->success instanceof Condition ? $this->success($modifiers->success) : '',
            $modifiers->failure instanceof Comparison ? 'f'.$this->threshold($modifiers->failure) : '',
            $modifiers->occurrences instanceof Occurrences ? $this->occurrences($modifiers->occurrences) : '',
            null === $modifiers->groups ? '' : 'g'.$modifiers->groups,
            $modifiers->criticalSuccess instanceof Critical ? 'cs'.$this->threshold($modifiers->criticalSuccess->on) : '',
            $modifiers->criticalFailure instanceof Critical ? 'cf'.$this->threshold($modifiers->criticalFailure->on) : '',
            [] === $modifiers->looks ? '' : 'look['.implode(',', array_map(static fn (Look $look): string => (null === $look->count ? '' : $look->count.':').$look->token, $modifiers->looks)).']',
            $modifiers->sort instanceof Sort ? $modifiers->sort->value : '',
        ]);
    }

    private function explosion(Explosion $explosion): string
    {
        return ($explosion->compound ? '!!' : '!').($explosion->penetrating ? 'p' : '').$this->threshold($explosion->on)
            .($explosion->limit instanceof Node ? '('.$this->writeNode($explosion->limit).')' : '');
    }

    /**
     * Successes judged on each final value by a plain comparison write it alone: `>=7`; any other `c[...]`.
     */
    private function success(Condition $success): string
    {
        return Scope::Value === $success->scope && $success->validator instanceof Comparison && $success->validator->isPlain()
            ? $success->validator->comparator->value.$success->validator->value
            : 'c['.$this->condition($success).']';
    }

    private function occurrences(Occurrences $occurrences): string
    {
        return 'o'.$occurrences->width.($occurrences->height instanceof Validator ? '['.$this->writeValidator($occurrences->height).']' : '');
    }

    /**
     * A threshold: a face alone, unless it is negative; a plain comparison with its sign; any other validator between
     * brackets.
     */
    private function threshold(?Validator $validator): string
    {
        return match (true) {
            !$validator instanceof Validator => '',
            !$validator instanceof Comparison || !$validator->isPlain() => '['.$this->writeValidator($validator).']',
            Comparator::Equal === $validator->comparator && $validator->value >= 0 => (string) $validator->value,
            default => $validator->comparator->value.$validator->value,
        };
    }

    private function condition(Condition $condition): string
    {
        return (Scope::Value === $condition->scope ? '' : $condition->scope->value.':').$this->writeValidator($condition->validator);
    }

    /**
     * An operand of a composite, between parentheses when it binds looser than the composite needs.
     */
    private function operand(Validator $validator, int $precedence): string
    {
        return $validator instanceof Composite && $validator->junction->precedence() < $precedence ? '('.$this->writeValidator($validator).')' : $this->writeValidator($validator);
    }

    /**
     * A validator that joins none.
     */
    private function atom(Validator $validator): string
    {
        return match (true) {
            $validator instanceof Comparison => Comparator::Equal === $validator->comparator ? (string) $validator->value : $validator->comparator->value.$validator->value,
            $validator instanceof Range => $validator->from.'..'.$validator->to,
            $validator instanceof Modulo => '%'.$validator->divisor.$validator->remainder->comparator->value.$validator->remainder->value,
            default => throw InvalidDefinition::because(\sprintf('%s is not a validator the library writes.', $validator::class)),
        };
    }
}
