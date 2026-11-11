<?php

declare(strict_types=1);

namespace Tilttek\Dice\Parser;

use Tilttek\Dice\Contract\Macros;
use Tilttek\Dice\Die\CustomDie;
use Tilttek\Dice\Die\DieType;
use Tilttek\Dice\Die\FudgeDie;
use Tilttek\Dice\Die\RangeDie;
use Tilttek\Dice\Die\StandardDie;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
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
use Tilttek\Dice\Expression\Junction;
use Tilttek\Dice\Expression\ListItem;
use Tilttek\Dice\Expression\Look;
use Tilttek\Dice\Expression\MathFunction;
use Tilttek\Dice\Expression\Modifiers;
use Tilttek\Dice\Expression\Modulo;
use Tilttek\Dice\Expression\Negation;
use Tilttek\Dice\Expression\Node;
use Tilttek\Dice\Expression\Occurrences;
use Tilttek\Dice\Expression\Operation;
use Tilttek\Dice\Expression\Operator;
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
use Tilttek\Dice\Expression\SelectionMode;
use Tilttek\Dice\Expression\Sentence;
use Tilttek\Dice\Expression\Sort;
use Tilttek\Dice\Expression\SwitchCase;
use Tilttek\Dice\Expression\TextList;
use Tilttek\Dice\Expression\Transform;
use Tilttek\Dice\Expression\Validator;
use Tilttek\Dice\Expression\ValueList;
use Tilttek\Dice\Limits;
use Tilttek\Dice\Notation\Notation;

/**
 * Parses an input into a pool of instructions, in a language's notation or the canonical one, letters in either case:
 * `4D6KH3` is `4d6kh3`, `d%` is `1d100`, `d20 adv` is `2d20kh1`, `4d6k3` keeps the highest and `4d6d1` drops the
 * lowest. Instructions are separated by `;` or `,`, and refer only to those before them. Comments are kept by the
 * instruction they are written in. With macros, `$name` is the tree of the macro of that name, and a macro of several
 * instructions written alone is the whole pool. An invalid input is refused with the position of the error; one past
 * the limits is refused too.
 */
final class DiceParser
{
    /**
     * The largest number an input may write, and how deep parentheses, negations and functions may nest.
     */
    public const int MAX_NUMBER = 1_000_000_000;

    public const int MAX_DEPTH = 32;

    private string $input = '';

    /**
     * @var list<Token>
     */
    private array $tokens = [];

    private int $index = 0;

    private int $depth = 0;

    /**
     * Whether a set of dice comes before, in the expression being read, for "@".
     */
    private bool $sets = false;

    /**
     * The number of instructions read before the one being read.
     */
    private int $instruction = 0;

    /**
     * The instructions, by their number from 1, whose dice went to a merge.
     *
     * @var list<int>
     */
    private array $merged = [];

    /**
     * Whether the parser is in a branch of an `if` or a `switch`.
     */
    private bool $branch = false;

    /**
     * @var array<int, list<string>>
     */
    private array $comments = [];

    /**
     * The pool of a macro of several instructions written alone, which the input stands for.
     */
    private ?Pool $whole = null;

    public function __construct(
        private readonly Limits $limits = new Limits(),
        private readonly Notation $notation = new Notation(),
        private readonly ?Macros $macros = null,
    ) {
    }

    /**
     * @throws InvalidExpression when the input does not parse
     * @throws LimitExceeded     when it parses but goes past a limit
     */
    public function parse(string $input): Pool
    {
        $this->limits->checkLength($input);
        $this->input = $input;
        $tokens = Lexer::tokenize($input, $this->notation->die);
        $this->tokens = array_values(array_filter($tokens, static fn (Token $token): bool => TokenType::Comment !== $token->type));
        $this->index = 0;
        $this->depth = 0;
        $this->sets = false;
        $this->instruction = 0;
        $this->merged = [];
        $this->branch = false;
        $this->comments = [];
        $this->whole = null;

        $expressions = [$this->expression()];
        $ends = [];

        while ($this->peek()->is(TokenType::Symbol, ',') || $this->peek()->is(TokenType::Symbol, ';')) {
            $ends[] = $this->next()->position;
            $this->sets = false;
            ++$this->instruction;
            $expressions[] = $this->expression();
        }

        $this->expect(TokenType::End);

        if ($this->whole instanceof Pool) {
            $this->limits->check($this->whole);

            return $this->whole;
        }

        foreach ($tokens as $token) {
            if (TokenType::Comment === $token->type) {
                $this->instruction = \count(array_filter($ends, static fn (int $end): bool => $end < $token->position));
                $this->remark($token->text);
            }
        }

        ksort($this->comments);
        $pool = new Pool(...$expressions)->withComments($this->comments);
        $this->limits->check($pool);

        return $pool;
    }

    private function expression(): Node
    {
        $node = $this->term();

        while (($operator = $this->operator(Operator::Add, Operator::Subtract)) instanceof Operator) {
            $position = $this->tokens[$this->index - 1]->position;
            $node = new Operation($operator, $this->numeric($node, $position), $this->numeric($this->term(), $position));
        }

        return $node;
    }

    private function term(): Node
    {
        $node = $this->unary();

        while (($operator = $this->operator(Operator::Multiply, Operator::Divide, Operator::Modulo)) instanceof Operator) {
            $position = $this->tokens[$this->index - 1]->position;
            $node = new Operation($operator, $this->numeric($node, $position), $this->numeric($this->unary(), $position));
        }

        return $node;
    }

    private function unary(): Node
    {
        if (!$this->peek()->is(TokenType::Symbol, '-')) {
            return $this->power();
        }

        $position = $this->peek()->position;
        $this->enter();
        $node = new Negation($this->numeric($this->unary(), $position));
        --$this->depth;

        return $node;
    }

    private function power(): Node
    {
        $base = $this->postfix($this->primary());

        if (!$this->peek()->is(TokenType::Symbol, '^')) {
            return $base;
        }

        $position = $this->next()->position;

        return new Operation(Operator::Power, $this->numeric($base, $position), $this->numeric($this->unary(), $position));
    }

    /**
     * What follows an operand: an `if`, a `switch`, a comment between brackets.
     */
    private function postfix(Node $node): Node
    {
        while (true) {
            $token = $this->peek();

            if ($token->isLetter('i') && $this->tokens[$this->index + 1]->is(TokenType::Symbol, '[')) {
                $node = $this->conditional($node, $token);
            } elseif ($token->isLetter('s') && $this->switchFollows()) {
                $node = $this->choice($node, $token);
            } elseif ($token->is(TokenType::Symbol, '[')) {
                $this->comment();
            } else {
                return $node;
            }
        }
    }

    private function primary(): Node
    {
        $token = $this->peek();

        if ($token->is(TokenType::Symbol, '(')) {
            $node = $this->parenthesized();

            return match (true) {
                $this->dieFollows() => $this->dice($this->numeric($node, $token->position), $token->position),
                $this->listFollows() => $this->textList($this->numeric($node, $token->position), $token->position),
                default => $node,
            };
        }

        return match (true) {
            $token->is(TokenType::Symbol, '[') => $this->values(),
            $token->is(TokenType::Symbol, '@') => $this->previous(),
            $token->is(TokenType::Symbol, '$') => ($macros = $this->macrosFollowing()) instanceof Macros ? $this->macro($macros) : $this->reference(),
            $token->is(TokenType::Symbol, '{') => $this->group(),
            $token->is(TokenType::Text) => $this->sentence(),
            default => $this->named($token),
        };
    }

    /**
     * A function, the dice of instructions before, dice, a text list or a number.
     */
    private function named(Token $token): Node
    {
        if (($function = $this->function()) instanceof MathFunction) {
            return $this->call($function, $token);
        }

        if (($token->isLetter('m') || $token->isLetter('b')) && $this->tokens[$this->index + 1]->is(TokenType::Symbol, '[')) {
            return $this->instructions($token->isLetter('m'));
        }

        $count = $token->is(TokenType::Number) ? $this->number() : null;

        if ($this->dieFollows()) {
            return $this->dice($count ?? 1, $token->position);
        }

        if ($this->listFollows()) {
            return $this->textList(new Constant($count ?? 1), $token->position);
        }

        if (null === $count) {
            throw $this->unexpected($token);
        }

        return new Constant($count);
    }

    /**
     * Refuses a text or a list where a number is computed.
     */
    private function numeric(Node $node, int $position): Node
    {
        if ($node instanceof Sentence || $node instanceof TextList || ($node instanceof FunctionCall && MathFunction::Repeat === $node->function)) {
            throw InvalidExpression::at($this->input, $position, 'A text or a list is not a number to compute with');
        }

        return $node;
    }

    private function parenthesized(): Node
    {
        $this->enter();
        $node = $this->expression();
        $this->expect(TokenType::Symbol, ')');
        --$this->depth;

        return $node;
    }

    /**
     * Steps over a sign that nests what follows — a parenthesis, a negation, a function —, within the depth allowed.
     */
    private function enter(): void
    {
        if (++$this->depth > self::MAX_DEPTH) {
            throw InvalidExpression::at($this->input, $this->peek()->position, 'Too deeply nested');
        }

        ++$this->index;
    }

    private function dieFollows(): bool
    {
        return TokenType::Letter === $this->peek()->type && $this->notation->isDie($this->peek()->text);
    }

    /**
     * The function whose name comes next, followed by a parenthesis.
     */
    private function function(): ?MathFunction
    {
        foreach (MathFunction::cases() as $function) {
            if ($this->spells($function->value) && $this->tokens[$this->index + \strlen($function->value)]->is(TokenType::Symbol, '(')) {
                $this->index += \strlen($function->value);

                return $function;
            }
        }

        return null;
    }

    private function call(MathFunction $function, Token $name): FunctionCall
    {
        $this->enter();
        $arguments = [$this->argument($function, 0)];

        while ($this->accept(TokenType::Symbol, ',')) {
            $arguments[] = $this->argument($function, \count($arguments));
        }

        $this->expect(TokenType::Symbol, ')');
        --$this->depth;

        try {
            return new FunctionCall($function, ...$arguments);
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($name, $invalidDefinition);
        }
    }

    /**
     * An argument of a function: a number, but for what `repeat()` rolls, which may be a text or a list.
     */
    private function argument(MathFunction $function, int $index): Node
    {
        $position = $this->peek()->position;
        $argument = $this->expression();

        return MathFunction::Repeat === $function && 0 === $index ? $argument : $this->numeric($argument, $position);
    }

    private function dice(Node|int $count, int $start): Node
    {
        $letter = $this->next();
        $die = $this->dieType($letter);
        $die = $die instanceof Constant ? $this->sides($die->value, $letter) : $die;

        $count = $count instanceof Constant ? $count->value : $count;
        [$modifiers, $advantage] = $this->modifiers($start);
        $this->sets = true;

        if ($advantage && 1 !== $count) {
            throw InvalidExpression::at($this->input, $start, 'Advantage and disadvantage roll one die twice, and select it themselves');
        }

        try {
            if (\is_int($count) && $die instanceof DieType) {
                return new Dice($advantage ? 2 : $count, $die, $modifiers);
            }

            return new ComputedDice(\is_int($count) ? new Constant($count) : $count, $die, $modifiers);
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidExpression::at($this->input, $start, rtrim($invalidDefinition->getMessage(), '.'));
        }
    }

    private function dieType(Token $letter): DieType|Node
    {
        $token = $this->peek();

        if ($token->isLetter('f') && $token->follows($letter)) {
            ++$this->index;

            return $this->accept(TokenType::Symbol, '.') ? $this->fudge($this->peek()) : new FudgeDie();
        }

        if ($this->accept(TokenType::Symbol, '%')) {
            return new StandardDie(100);
        }

        if ($token->is(TokenType::Name)) {
            ++$this->index;

            try {
                return new CustomDie(strtolower(trim($token->text)));
            } catch (InvalidDefinition $exception) {
                throw $this->invalid($token, $exception);
            }
        }

        if ($token->is(TokenType::Symbol, '[')) {
            return $this->range();
        }

        if ($token->is(TokenType::Symbol, '(')) {
            return $this->numeric($this->parenthesized(), $token->position);
        }

        if (!$token->is(TokenType::Number)) {
            throw InvalidExpression::at($this->input, $token->position, 'A die needs its number of sides, "%", "F", a range between brackets, an expression between parentheses or a name between braces');
        }

        return $this->sides($this->number(), $token);
    }

    private function sides(int $sides, Token $token): StandardDie
    {
        if ($sides < 1) {
            throw InvalidExpression::at($this->input, $token->position, 'A die has at least one side');
        }

        return new StandardDie($sides);
    }

    private function fudge(Token $token): FudgeDie
    {
        try {
            return new FudgeDie($this->expectNumber());
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }
    }

    private function range(): RangeDie
    {
        $token = $this->next();
        $from = $this->signedNumber();
        $this->expectRange();
        $to = $this->signedNumber();
        $this->expect(TokenType::Symbol, ']');

        try {
            return new RangeDie($from, $to);
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }
    }

    private function values(): ValueList
    {
        $token = $this->next();
        $values = [$this->signedNumber()];

        while ($this->accept(TokenType::Symbol, ',')) {
            $values[] = $this->signedNumber();
        }

        $this->expect(TokenType::Symbol, ']');
        [$modifiers] = $this->modifiers($token->position, false);
        $this->sets = true;

        try {
            return new ValueList($values, $modifiers);
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }
    }

    private function previous(): PreviousDice|InstructionDice
    {
        $token = $this->next();

        if ($this->peek()->is(TokenType::Number) && $this->peek()->follows($token)) {
            return $this->instructionDice([$this->instructionNumber(true)], $token, false);
        }

        if (!$this->sets) {
            throw InvalidExpression::at($this->input, $token->position, '"@" judges the dice of a set before it, and none comes before');
        }

        [$modifiers] = $this->modifiers($token->position, false);

        try {
            return new PreviousDice($modifiers);
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }
    }

    /**
     * The result of an instruction before: `$1`, or one of its items, `$1[0]`.
     */
    /**
     * The macros, when the parser reads them and a macro's name comes next: a letter written against the `$`.
     */
    private function macrosFollowing(): ?Macros
    {
        $name = $this->tokens[$this->index + 1];

        return $name->is(TokenType::Letter) && $name->follows($this->peek()) ? $this->macros : null;
    }

    private function macro(Macros $macros): Node
    {
        $alone = 0 === $this->index;
        $sign = $this->next();
        $token = $this->next();
        $name = $token->text;

        while (($next = $this->peek())->follows($token) && ($next->is(TokenType::Letter) || $next->is(TokenType::Number))) {
            $name .= $next->text;
            $token = $this->next();
        }

        $name = strtolower($name);

        // A letter, then letters and digits: the tokens read are those; only the length is left to check.
        if (\strlen($name) > 32) {
            throw InvalidExpression::at($this->input, $sign->position, \sprintf('A macro is named by a letter, then letters and digits, 32 at most; "%s" is not', $name));
        }

        try {
            $pool = $macros->pool($name);
        } catch (InvalidExpression $invalidExpression) {
            throw InvalidExpression::at($this->input, $sign->position, \sprintf('The macro "%s" does not parse: %s', $name, $invalidExpression->reason));
        }

        if (!$pool instanceof Pool) {
            throw InvalidExpression::at($this->input, $sign->position, \sprintf('No macro is named "%s"', $name));
        }

        if (\count($pool->expressions) > 1) {
            if (!$alone || !$this->peek()->is(TokenType::End)) {
                throw InvalidExpression::at($this->input, $sign->position, \sprintf('The macro "%s" holds several instructions: it is written alone', $name));
            }

            $this->whole = $pool;
        }

        return $pool->expressions[0];
    }

    private function reference(): Reference
    {
        $token = $this->next();

        if (!$this->peek()->is(TokenType::Number) || !$this->peek()->follows($token)) {
            throw $this->unexpected($this->peek());
        }

        $instruction = $this->instructionNumber(false);
        $index = null;

        if ($this->peek()->is(TokenType::Symbol, '[') && $this->tokens[$this->index + 1]->is(TokenType::Number) && $this->tokens[$this->index + 2]->is(TokenType::Symbol, ']')) {
            ++$this->index;
            $index = $this->number();
            ++$this->index;
        }

        return new Reference($instruction, $index);
    }

    /**
     * The number of an instruction that comes before the one being read, and has not given its dice to a merge when
     * its dice are taken.
     */
    private function instructionNumber(bool $dice): int
    {
        $token = $this->peek();
        $instruction = $this->expectNumber();

        if ($instruction < 1 || $instruction > $this->instruction) {
            throw InvalidExpression::at($this->input, $token->position, \sprintf('Instruction %d does not come before this one', $instruction));
        }

        if ($dice && \in_array($instruction, $this->merged, true)) {
            throw InvalidExpression::at($this->input, $token->position, \sprintf('Instruction %d gave its dice to a merge', $instruction));
        }

        return $instruction;
    }

    /**
     * The dice of several instructions, bound by `b[…]` or merged by `m[…]`.
     */
    private function instructions(bool $merge): InstructionDice
    {
        $token = $this->next();
        $this->expect(TokenType::Symbol, '[');
        $instructions = [$this->instructionNumber(true)];

        while ($this->accept(TokenType::Symbol, ',')) {
            $instructions[] = $this->instructionNumber(true);
        }

        $this->expect(TokenType::Symbol, ']');

        return $this->instructionDice($instructions, $token, $merge);
    }

    /**
     * @param list<int> $instructions
     */
    private function instructionDice(array $instructions, Token $token, bool $merge): InstructionDice
    {
        [$modifiers] = $this->modifiers($token->position, false);

        try {
            $dice = new InstructionDice($instructions, $modifiers, $merge);
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }

        if ($merge) {
            $this->merged = [...$this->merged, ...$instructions];
        }

        $this->sets = true;

        return $dice;
    }

    /**
     * Expressions between braces, each rolled with its own total, the totals judged as dice.
     */
    private function group(): Group
    {
        $token = $this->peek();
        $this->enter();
        $expressions = [];

        do {
            $this->sets = false;
            $position = $this->peek()->position;
            $expressions[] = $this->numeric($this->expression(), $position);
        } while ($this->accept(TokenType::Symbol, ','));

        $this->expect(TokenType::Symbol, '}');
        --$this->depth;
        [$modifiers] = $this->modifiers($token->position, false);
        $this->sets = true;

        try {
            return new Group($expressions, $modifiers);
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }
    }

    /**
     * Whether `L` comes next, with the letters that say how it draws — `u`, `n` — and the bracket of its items, all
     * written together.
     */
    private function listFollows(): bool
    {
        if (!$this->peek()->isLetter('l')) {
            return false;
        }

        $offset = 1;

        while ($this->tokens[$this->index + $offset]->follows($this->tokens[$this->index + $offset - 1]) && ($this->tokens[$this->index + $offset]->isLetter('u') || $this->tokens[$this->index + $offset]->isLetter('n'))) {
            ++$offset;
        }

        return $this->tokens[$this->index + $offset]->is(TokenType::Symbol, '[') && $this->tokens[$this->index + $offset]->follows($this->tokens[$this->index + $offset - 1]);
    }

    private function textList(Node $count, int $start): TextList
    {
        ++$this->index;
        $unique = false;
        $joined = false;

        while (!$this->peek()->is(TokenType::Symbol, '[')) {
            $flag = $this->next();

            if (($flag->isLetter('u') && $unique) || ($flag->isLetter('n') && $joined)) {
                throw InvalidExpression::at($this->input, $flag->position, 'A modifier is given twice');
            }

            $unique = $unique || $flag->isLetter('u');
            $joined = $joined || $flag->isLetter('n');
        }

        ++$this->index;
        $items = [$this->listItem()];

        while ($this->accept(TokenType::Symbol, ',')) {
            $items[] = $this->listItem();
        }

        $this->expect(TokenType::Symbol, ']');

        try {
            return new TextList($count, $items, $unique, $joined);
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidExpression::at($this->input, $start, rtrim($invalidDefinition->getMessage(), '.'));
        }
    }

    /**
     * An item of a text list: its range, its text — between double quotes, or as written up to the comma, the bracket
     * or the weight that ends it —, and its weight.
     */
    private function listItem(): ListItem
    {
        $token = $this->peek();

        try {
            $range = $this->itemRange();
            $text = $this->itemText();
            $weight = 1;

            if ($this->accept(TokenType::Symbol, '[')) {
                $weight = $this->expectNumber();
                $this->expect(TokenType::Symbol, ']');
            }

            return new ListItem($text, $weight, $range);
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }
    }

    /**
     * The range an item covers, `1..3:`, when it starts with one.
     *
     * @throws InvalidDefinition when the range goes downwards
     */
    private function itemRange(): ?Range
    {
        $start = $this->index;

        try {
            $from = $this->optionalNumber();

            if (null !== $from && $this->accept(TokenType::Symbol, '.') && $this->accept(TokenType::Symbol, '.')) {
                $to = $this->optionalNumber();

                if (null !== $to && $this->accept(TokenType::Symbol, ':')) {
                    return new Range($from, $to);
                }
            }
        } catch (InvalidExpression) {
            // A number too large starts a text, not a range.
        }

        $this->index = $start;

        return null;
    }

    /**
     * A number, maybe negative, when one comes next.
     */
    private function optionalNumber(): ?int
    {
        if ($this->peek()->is(TokenType::Symbol, '-') && $this->tokens[$this->index + 1]->is(TokenType::Number)) {
            ++$this->index;

            return -$this->number();
        }

        return $this->peek()->is(TokenType::Number) ? $this->number() : null;
    }

    private function itemText(): string
    {
        $token = $this->peek();

        if ($token->is(TokenType::Text) && $this->endsItem($this->tokens[$this->index + 1])) {
            ++$this->index;

            return $this->unescapeText($token);
        }

        while (!$this->endsItem($this->peek())) {
            ++$this->index;
        }

        $text = trim(mb_substr($this->input, $token->position, $this->peek()->position - $token->position));

        return '' === $text ? throw $this->unexpected($this->peek()) : $text;
    }

    private function endsItem(Token $token): bool
    {
        return TokenType::End === $token->type || $token->is(TokenType::Symbol, ',') || $token->is(TokenType::Symbol, ']') || $token->is(TokenType::Symbol, '[');
    }

    private function sentence(): Sentence
    {
        return new Sentence($this->unescape($this->next()));
    }

    /**
     * The text of an item between double quotes, its escapes read.
     */
    private function unescapeText(Token $token): string
    {
        $characters = mb_str_split($token->text);
        $count = \count($characters);
        $text = '';

        for ($offset = 0; $offset < $count; ++$offset) {
            // After the escape is stepped over, the offset of the backslash in the input is the offset of the text's
            // first character, one past the quote, plus the offset of the backslash in the text.
            $text .= '\\' === $characters[$offset] ? $this->escaped($characters[++$offset], $token->position + $offset) : $characters[$offset];
        }

        return $text;
    }

    /**
     * The text of a sentence, its escapes and its placeholders read.
     *
     * @return list<string|Placeholder>
     */
    private function unescape(Token $token): array
    {
        $characters = mb_str_split($token->text);
        $count = \count($characters);
        $parts = [];

        for ($offset = 0; $offset < $count; ++$offset) {
            $character = $characters[$offset];
            $position = $token->position + 1 + $offset;

            if ('\\' === $character) {
                $parts[] = $this->escaped($characters[++$offset], $position);
            } elseif (\in_array($character, ['%', '$', '@'], true) && ctype_digit($characters[$offset + 1] ?? '')) {
                $digits = '';

                while (ctype_digit($characters[$offset + 1] ?? '')) {
                    $digits .= $characters[++$offset];
                }

                [$parts[], $offset] = $this->placeholder($character, $digits, $characters, $offset, $position);
            } else {
                $parts[] = $character;
            }
        }

        return $parts;
    }

    /**
     * The character a backslash escapes: `\n` is a line break; `\`, `"`, `%`, `$`, `@`, `[` and the digits are written
     * as such.
     */
    private function escaped(string $escaped, int $position): string
    {
        return match ($escaped) {
            'n' => "\n",
            '\\', '"', '%', '$', '@', '[' => $escaped,
            default => ctype_digit($escaped) ? $escaped : throw InvalidExpression::at($this->input, $position, \sprintf('Unknown escape "\\%s"', $escaped)),
        };
    }

    /**
     * A placeholder of a sentence, and the offset of its last character.
     *
     * @param list<string> $characters
     *
     * @return array{Placeholder, int}
     */
    private function placeholder(string $sign, string $digits, array $characters, int $offset, int $position): array
    {
        // Digits beyond the range of an integer convert to its largest value, and are refused all the same.
        $number = (int) $digits;

        if ('%' === $sign) {
            $kind = match ($digits) {
                '1' => PlaceholderKind::Subject,
                '2' => PlaceholderKind::SubjectDice,
                '3' => PlaceholderKind::Before,
                default => throw InvalidExpression::at($this->input, $position, \sprintf('Unknown placeholder "%%%s"', $digits)),
            };

            if (0 === $this->instruction && (PlaceholderKind::Before === $kind || !$this->branch)) {
                throw InvalidExpression::at($this->input, $position, \sprintf('"%%%s" speaks of %s, and none comes before', $digits, PlaceholderKind::Before === $kind ? 'the instructions before' : 'the subject of a branch or the instruction before'));
            }

            return [new Placeholder($kind), $offset];
        }

        if ($number < 1 || $number > $this->instruction) {
            throw InvalidExpression::at($this->input, $position, \sprintf('Instruction %s does not come before this one', $digits));
        }

        $index = null;

        if ('$' === $sign && '[' === ($characters[$offset + 1] ?? '')) {
            $end = $offset + 2;
            $item = '';

            while (ctype_digit($characters[$end] ?? '')) {
                $item .= $characters[$end++];
            }

            if ('' !== $item && ']' === ($characters[$end] ?? '')) {
                $index = (int) $item;
                $offset = $end;
            }
        }

        return [new Placeholder('$' === $sign ? PlaceholderKind::Result : PlaceholderKind::Dice, $number, $index), $offset];
    }

    /**
     * Whether a switch comes next: `S[`, or `S^[` when exclusive.
     */
    private function switchFollows(): bool
    {
        $next = $this->tokens[$this->index + 1];

        return $next->is(TokenType::Symbol, '[') || ($next->is(TokenType::Symbol, '^') && $this->tokens[$this->index + 2]->is(TokenType::Symbol, '['));
    }

    private function conditional(Node $subject, Token $token): Conditional
    {
        $this->numeric($subject, $token->position);
        ++$this->index;
        $condition = $this->bracketed($this->condition(...));
        $then = $this->branch();
        $else = $this->peek()->is(TokenType::Symbol, '{') ? $this->branch() : null;

        try {
            return new Conditional($subject, $condition, $then, $else);
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }
    }

    private function choice(Node $subject, Token $token): Choice
    {
        $this->numeric($subject, $token->position);
        ++$this->index;
        $exclusive = $this->accept(TokenType::Symbol, '^');
        $cases = [];

        try {
            while ($this->peek()->is(TokenType::Symbol, '[')) {
                $when = $this->bracketed($this->condition(...));
                $cases[] = new SwitchCase($when, $this->branch());
            }

            return new Choice($subject, $cases, $this->peek()->is(TokenType::Symbol, '{') ? $this->branch() : null, $exclusive);
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }
    }

    /**
     * A branch between braces: anything an instruction may be.
     */
    private function branch(): Node
    {
        if (!$this->peek()->is(TokenType::Symbol, '{')) {
            throw $this->unexpected($this->peek());
        }

        $this->enter();
        $outer = $this->branch;
        $this->branch = true;
        $node = $this->expression();
        $this->branch = $outer;
        $this->expect(TokenType::Symbol, '}');
        --$this->depth;

        return $node;
    }

    /**
     * A comment between brackets, after an operand: everything up to the closing bracket.
     */
    private function comment(): void
    {
        $open = $this->next();

        while (!$this->peek()->is(TokenType::Symbol, ']')) {
            if (TokenType::End === $this->peek()->type) {
                throw $this->unexpected($this->peek());
            }

            ++$this->index;
        }

        $close = $this->next();
        $this->remark(trim(mb_substr($this->input, $open->position + 1, $close->position - $open->position - 1)));
    }

    /**
     * Keeps a comment with the instruction being read.
     */
    private function remark(string $comment): void
    {
        if ('' !== $comment) {
            $this->comments[$this->instruction][] = $comment;
        }
    }

    /**
     * The modifiers that follow a set of dice, in any order, each given once, and whether they ask for advantage —
     * which only rolled dice may.
     *
     * @return array{Modifiers, bool}
     */
    private function modifiers(int $start, bool $rolled = true): array
    {
        $reroll = null;
        $unique = null;
        $explosion = null;
        $allSame = null;
        $minimum = null;
        $maximum = null;
        $transforms = null;
        $filter = null;
        $selection = null;
        $success = null;
        $failure = null;
        $occurrences = null;
        $groups = null;
        $criticalSuccess = null;
        $criticalFailure = null;
        $looks = null;
        $sort = null;
        $advantage = null;
        while (true) {
            $token = $this->peek();

            if ($rolled && ($word = $this->advantage()) instanceof SelectionMode) {
                $advantage = $this->once($advantage, $token, $word);
            } elseif ($this->word('min')) {
                $minimum = $this->once($minimum, $token, $this->signedNumber());
            } elseif ($this->word('max')) {
                $maximum = $this->once($maximum, $token, $this->signedNumber());
            } elseif ($this->spells('look') && $this->tokens[$this->index + 4]->is(TokenType::Symbol, '[')) {
                $this->index += 4;
                $looks = $this->once($looks, $token, $this->looks());
            } elseif ($token->isLetter('k') || ($token->isLetter('d') && $this->dropFollows())) {
                $selection = $this->once($selection, $token, $this->selection());
            } elseif ($token->isLetter('r')) {
                $reroll = $this->once($reroll, $token, $this->reroll());
            } elseif ($token->isLetter('u')) {
                $unique = $this->once($unique, $token, $this->unique());
            } elseif ($token->isLetter('a')) {
                ++$this->index;
                $explosion = $this->once($explosion, $token, new Explosion($this->threshold() ?? throw $this->unexpected($this->peek()), true, limit: new Constant(1)));
            } elseif ($token->is(TokenType::Symbol, '!')) {
                $explosion = $this->once($explosion, $token, $this->explosion());
            } elseif ($token->isLetter('t') && $this->tokens[$this->index + 1]->is(TokenType::Symbol, '[')) {
                ++$this->index;
                $transforms = $this->once($transforms, $token, $this->transforms());
            } elseif ($token->isLetter('t')) {
                ++$this->index;
                $allSame = $this->once($allSame, $token, true);
            } elseif ($token->isLetter('f') && $this->tokens[$this->index + 1]->is(TokenType::Symbol, '[')) {
                ++$this->index;
                $filter = $this->once($filter, $token, $this->bracketed($this->condition(...)));
            } elseif ($token->isLetter('f')) {
                ++$this->index;
                $failure = $this->once($failure, $token, $this->plain() ?? throw $this->unexpected($this->peek()));
            } elseif ($token->isLetter('c') && $this->adjacentLetter('s')) {
                $this->index += 2;
                $criticalSuccess = $this->once($criticalSuccess, $token, new Critical($this->threshold()));
            } elseif ($token->isLetter('c') && $this->adjacentLetter('f')) {
                $this->index += 2;
                $criticalFailure = $this->once($criticalFailure, $token, new Critical($this->threshold()));
            } elseif ($token->isLetter('c')) {
                ++$this->index;
                $success = $this->once($success, $token, $this->bracketed($this->condition(...)));
            } elseif ($token->is(TokenType::Compare)) {
                $success = $this->once($success, $token, new Condition($this->comparison()));
            } elseif ($token->isLetter('o')) {
                $occurrences = $this->once($occurrences, $token, $this->occurrences());
            } elseif ($token->isLetter('g')) {
                ++$this->index;
                $groups = $this->once($groups, $token, $this->expectNumber());
            } elseif ($token->isLetter('s') && !$this->switchFollows()) {
                $sort = $this->once($sort, $token, $this->sort());
            } else {
                break;
            }
        }

        if ($advantage instanceof SelectionMode) {
            if ($selection instanceof Selection) {
                throw InvalidExpression::at($this->input, $start, 'Advantage and disadvantage roll one die twice, and select it themselves');
            }

            $selection = new Selection($advantage, 1);
        }

        try {
            return [new Modifiers(
                $reroll,
                $unique,
                $explosion,
                true === $allSame,
                $minimum,
                $maximum,
                $transforms ?? [],
                $filter,
                $selection,
                $success,
                $failure,
                $occurrences,
                $groups,
                $criticalSuccess,
                $criticalFailure,
                $looks ?? [],
                $sort,
            ), $advantage instanceof SelectionMode];
        } catch (InvalidDefinition $invalidDefinition) {
            throw InvalidExpression::at($this->input, $start, rtrim($invalidDefinition->getMessage(), '.'));
        }
    }

    /**
     * The word of advantage or disadvantage, its letters written together, when it comes next.
     */
    private function advantage(): ?SelectionMode
    {
        foreach ([[$this->notation->advantage, SelectionMode::KeepHighest], [$this->notation->disadvantage, SelectionMode::KeepLowest]] as [$word, $mode]) {
            if ($this->word($word)) {
                return $mode;
            }
        }

        return null;
    }

    /**
     * Steps over a word when its letters come next, written together.
     */
    private function word(string $word): bool
    {
        if (!$this->spells($word)) {
            return false;
        }

        $this->index += \strlen($word);

        return true;
    }

    private function spells(string $word): bool
    {
        $first = $this->peek()->position;

        foreach (str_split(strtolower($word)) as $offset => $letter) {
            $token = $this->tokens[$this->index + $offset];

            if (!$token->isLetter($letter) || $token->position !== $first + $offset) {
                return false;
            }
        }

        return true;
    }

    /**
     * Whether the token after the next one is the letter given, written against it.
     */
    private function adjacentLetter(string $letter): bool
    {
        $token = $this->tokens[$this->index + 1];

        return $token->isLetter($letter) && $token->follows($this->peek());
    }

    /**
     * Whether the `d` that comes next drops dice — followed by `h`, `l` or a number — rather than starting anything else.
     */
    private function dropFollows(): bool
    {
        $after = $this->tokens[$this->index + 1];

        return $after->isLetter('h') || $after->isLetter('l') || $after->is(TokenType::Number);
    }

    private function selection(): Selection
    {
        $keeps = $this->next()->isLetter('k');
        $lowest = $keeps ? $this->peek()->isLetter('l') : !$this->peek()->isLetter('h');

        if ($this->peek()->isLetter('h') || $this->peek()->isLetter('l')) {
            ++$this->index;
        }

        $token = $this->peek();
        $count = $this->expectNumber();
        $mode = match (true) {
            $keeps && $lowest => SelectionMode::KeepLowest,
            $keeps => SelectionMode::KeepHighest,
            $lowest => SelectionMode::DropLowest,
            default => SelectionMode::DropHighest,
        };

        if (0 === $count) {
            throw InvalidExpression::at($this->input, $token->position, 'A selection keeps or drops at least one die');
        }

        return new Selection($mode, $count);
    }

    /**
     * `r` repeats the reroll, `ro` rerolls once.
     */
    private function reroll(): Reroll
    {
        $once = $this->adjacentLetter('o');
        $this->index += $once ? 2 : 1;

        return new Reroll($this->threshold() ?? throw $this->unexpected($this->peek()), $once ? Repetition::Once : Repetition::Repeated);
    }

    /**
     * `u` repeats the reroll of a value already shown, `uo` rerolls it once — unless the `o` counts occurrences, with
     * the width or the height that follows it.
     */
    private function unique(): Repetition
    {
        $once = $this->adjacentLetter('o') && !$this->tokens[$this->index + 2]->is(TokenType::Number) && !$this->tokens[$this->index + 2]->is(TokenType::Symbol, '[');
        $this->index += $once ? 2 : 1;

        return $once ? Repetition::Once : Repetition::Repeated;
    }

    private function explosion(): Explosion
    {
        $sign = $this->next();
        $compound = $this->peek()->is(TokenType::Symbol, '!') && $this->peek()->follows($sign);
        $this->index += $compound ? 1 : 0;
        $penetrating = $this->peek()->isLetter('p') && $this->peek()->follows($this->tokens[$this->index - 1]);
        $this->index += $penetrating ? 1 : 0;
        $on = $this->threshold();
        $token = $this->peek();
        $limit = $token->is(TokenType::Symbol, '(') ? $this->numeric($this->parenthesized(), $token->position) : null;

        try {
            return new Explosion($on, $compound, $penetrating, $limit);
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }
    }

    /**
     * @return list<Transform>
     */
    private function transforms(): array
    {
        $this->expect(TokenType::Symbol, '[');
        $transforms = [];

        do {
            $token = $this->peek();
            $when = $this->condition();
            $this->expect(TokenType::Symbol, ':');

            try {
                $transforms[] = new Transform($when, $this->signedNumber());
            } catch (InvalidDefinition $invalidDefinition) {
                throw $this->invalid($token, $invalidDefinition);
            }
        } while ($this->accept(TokenType::Symbol, ','));

        $this->expect(TokenType::Symbol, ']');

        return $transforms;
    }

    private function occurrences(): Occurrences
    {
        $token = $this->next();
        $width = $this->peek()->is(TokenType::Number) ? $this->number() : 2;
        $height = $this->peek()->is(TokenType::Symbol, '[') ? $this->bracketed($this->validator(...)) : null;

        try {
            return new Occurrences($width, $height);
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }
    }

    private function sort(): Sort
    {
        $descending = $this->adjacentLetter('d');
        $this->index += $descending || $this->adjacentLetter('a') ? 2 : 1;

        return $descending ? Sort::Descending : Sort::Ascending;
    }

    /**
     * @return list<Look>
     */
    private function looks(): array
    {
        $this->expect(TokenType::Symbol, '[');
        $looks = [];

        do {
            $token = $this->peek();
            $count = $token->is(TokenType::Number) ? $this->number() : null;

            if (null !== $count) {
                $this->expect(TokenType::Symbol, ':');
            }

            try {
                $looks[] = new Look($this->lookToken(), $count);
            } catch (InvalidDefinition $invalidDefinition) {
                throw $this->invalid($token, $invalidDefinition);
            }
        } while ($this->accept(TokenType::Symbol, ','));

        $this->expect(TokenType::Symbol, ']');

        return $looks;
    }

    /**
     * A look token: a letter, then the letters, digits and hyphens written against it.
     */
    private function lookToken(): string
    {
        $token = $this->next();

        if (TokenType::Letter !== $token->type) {
            throw $this->unexpected($token);
        }

        $text = $token->text;

        while (($next = $this->peek())->follows($token) && ($next->is(TokenType::Letter) || $next->is(TokenType::Number) || $next->is(TokenType::Symbol, '-'))) {
            $text .= $next->text;
            $token = $this->next();
        }

        return strtolower($text);
    }

    /**
     * After a reroll, an explosion or a critical mark: a face alone, a comparison or a validator between brackets;
     * nothing at all otherwise.
     */
    private function threshold(): ?Validator
    {
        if ($this->peek()->is(TokenType::Symbol, '[')) {
            return $this->bracketed($this->validator(...));
        }

        return $this->plain();
    }

    /**
     * A face alone or a comparison written with its sign; nothing at all otherwise.
     */
    private function plain(): ?Comparison
    {
        return match (true) {
            $this->peek()->is(TokenType::Number) => new Comparison(Comparator::Equal, $this->number()),
            $this->peek()->is(TokenType::Compare) => $this->comparison(),
            default => null,
        };
    }

    /**
     * @template T
     *
     * @param callable(): T $inside
     *
     * @return T
     */
    private function bracketed(callable $inside): mixed
    {
        $this->expect(TokenType::Symbol, '[');
        $value = $inside();
        $this->expect(TokenType::Symbol, ']');

        return $value;
    }

    /**
     * A validator and its scope, written before it with a colon: `all:>2`; each final value when none is written.
     */
    private function condition(): Condition
    {
        foreach ([Scope::Die, Scope::All, Scope::Any, Scope::Total] as $scope) {
            if ($this->spells($scope->value) && $this->tokens[$this->index + \strlen($scope->value)]->is(TokenType::Symbol, ':')) {
                $this->index += \strlen($scope->value) + 1;

                return new Condition($this->validator(), $scope);
            }
        }

        return new Condition($this->validator());
    }

    /**
     * Validators joined by `|` and `^`, of validators joined by `&`.
     */
    private function validator(): Validator
    {
        $validator = $this->conjunction();

        while (($junction = $this->junction(Junction::Or, Junction::Xor)) instanceof Junction) {
            $validator = new Composite($junction, $validator, $this->conjunction());
        }

        return $validator;
    }

    private function conjunction(): Validator
    {
        $validator = $this->atom();

        while ($this->junction(Junction::And) instanceof Junction) {
            $validator = new Composite(Junction::And, $validator, $this->atom());
        }

        return $validator;
    }

    private function junction(Junction ...$junctions): ?Junction
    {
        foreach ($junctions as $junction) {
            if ($this->accept(TokenType::Symbol, $junction->value)) {
                return $junction;
            }
        }

        return null;
    }

    /**
     * A validator between parentheses, a remainder, a comparison, a range or a value alone.
     */
    private function atom(): Validator
    {
        $token = $this->peek();

        if ($token->is(TokenType::Symbol, '(')) {
            $this->enter();
            $validator = $this->validator();
            $this->expect(TokenType::Symbol, ')');
            --$this->depth;

            return $validator;
        }

        if ($this->accept(TokenType::Symbol, '%')) {
            $divisor = $this->expectNumber();

            try {
                return new Modulo($divisor, $this->comparison());
            } catch (InvalidDefinition $invalidDefinition) {
                throw $this->invalid($token, $invalidDefinition);
            }
        }

        if ($token->is(TokenType::Compare) || $token->is(TokenType::Symbol, '!')) {
            return $this->comparison();
        }

        $from = $this->signedNumber();

        if (!$this->peek()->is(TokenType::Symbol, '.')) {
            return new Comparison(Comparator::Equal, $from);
        }

        $this->expectRange();

        try {
            return new Range($from, $this->signedNumber());
        } catch (InvalidDefinition $invalidDefinition) {
            throw $this->invalid($token, $invalidDefinition);
        }
    }

    /**
     * A comparator and a threshold, maybe negative; `!=` is read from its two signs.
     */
    private function comparison(): Comparison
    {
        if ($this->accept(TokenType::Symbol, '!')) {
            $this->expect(TokenType::Compare, '=');

            return new Comparison(Comparator::NotEqual, $this->signedNumber());
        }

        $token = $this->next();

        if (!$token->is(TokenType::Compare)) {
            throw $this->unexpected($token);
        }

        return new Comparison(Comparator::from($token->text), $this->signedNumber());
    }

    private function expectRange(): void
    {
        $this->expect(TokenType::Symbol, '.');
        $this->expect(TokenType::Symbol, '.');
    }

    /**
     * @template T
     *
     * @param T|null $current
     * @param T      $value
     *
     * @return T
     */
    private function once(mixed $current, Token $token, mixed $value): mixed
    {
        if (null !== $current) {
            throw InvalidExpression::at($this->input, $token->position, 'A modifier is given twice');
        }

        return $value;
    }

    private function operator(Operator ...$operators): ?Operator
    {
        foreach ($operators as $operator) {
            if ($this->accept(TokenType::Symbol, $operator->value)) {
                return $operator;
            }
        }

        return null;
    }

    private function signedNumber(): int
    {
        return $this->accept(TokenType::Symbol, '-') ? -$this->expectNumber() : $this->expectNumber();
    }

    private function expectNumber(): int
    {
        if (!$this->peek()->is(TokenType::Number)) {
            throw $this->unexpected($this->peek());
        }

        return $this->number();
    }

    private function number(): int
    {
        $token = $this->next();
        // Digits beyond the range of an integer convert to its largest value, and are refused all the same.
        $value = (int) $token->text;

        if ($value > self::MAX_NUMBER) {
            throw InvalidExpression::at($this->input, $token->position, \sprintf('A number is at most %d', self::MAX_NUMBER));
        }

        return $value;
    }

    /**
     * @phpstan-impure
     */
    private function accept(TokenType $type, string $text): bool
    {
        if ($this->peek()->is($type, $text)) {
            ++$this->index;

            return true;
        }

        return false;
    }

    private function expect(TokenType $type, ?string $text = null): void
    {
        if (!$this->peek()->is($type, $text)) {
            throw $this->unexpected($this->peek());
        }

        ++$this->index;
    }

    private function invalid(Token $token, InvalidDefinition $invalidDefinition): InvalidExpression
    {
        return InvalidExpression::at($this->input, $token->position, rtrim($invalidDefinition->getMessage(), '.'));
    }

    private function unexpected(Token $token): InvalidExpression
    {
        return InvalidExpression::at($this->input, $token->position, TokenType::End === $token->type ? 'Unexpected end' : \sprintf('Unexpected "%s"', $token->text));
    }

    private function peek(): Token
    {
        return $this->tokens[$this->index];
    }

    private function next(): Token
    {
        return $this->tokens[$this->index++];
    }
}
