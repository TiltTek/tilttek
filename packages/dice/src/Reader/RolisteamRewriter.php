<?php

declare(strict_types=1);

namespace Tilttek\Dice\Reader;

use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Parser\Lexer;
use Tilttek\Dice\Parser\Token;
use Tilttek\Dice\Parser\TokenType;

/**
 * Writes the tokens of a DiceParser command in the library's notation, one command at a time, and keeps where each
 * piece written comes from in the command: a token the notations share is copied with the spaces after it, a token
 * they write differently is replaced. Texts, names of functions and macros, and what is written between brackets —
 * lists, values, comments — are copied as they are, but for the scope a validator writes first.
 *
 * @internal
 */
final class RolisteamRewriter
{
    private const array FUNCTIONS = ['floor', 'ceil', 'round', 'abs', 'min', 'max', 'repeat'];

    /**
     * The scope a validator of DiceParser writes first, and the library's.
     */
    private const array SCOPES = ['*' => 'all:', '.' => 'any:', ':' => 'total:', '?' => ''];

    /**
     * The tokens a validator between brackets follows: counts, filters, rerolls, explosions, rerolls that add, ifs and
     * the cases of a switch.
     */
    private const array JUDGES = ['c', 'f', 'r', 'R', 'e', 'a', 'i', 'S', '^', '}'];

    /**
     * What a value of a transformation written by default judges: every value.
     */
    private const string EVERY_VALUE = '<1|>0';

    /**
     * @var list<Token>
     */
    private readonly array $tokens;

    /**
     * Each token as written, and the spaces after it, by its index.
     *
     * @var list<array{string, string}>
     */
    private readonly array $written;

    private readonly int $count;

    private int $index = 0;

    private string $output = '';

    private int $length = 0;

    /**
     * Where each piece of the output starts, the offset in the command it comes from, and how many of its characters
     * are copied from there, one for one.
     *
     * @var list<array{int, int, int}>
     */
    private array $pieces = [];

    /**
     * @throws InvalidExpression when a quote or a brace is not closed
     */
    public function __construct(
        private readonly string $input,
    ) {
        $characters = mb_str_split($input);
        $this->count = \count($characters);
        $this->tokens = Lexer::tokenize($input);
        $written = [];

        foreach (\array_slice($this->tokens, 0, -1) as $index => $token) {
            $next = $this->tokens[$index + 1]->position;
            $end = $next;

            while ($end > $token->position && ctype_space($characters[$end - 1])) {
                --$end;
            }

            $written[] = [implode('', \array_slice($characters, $token->position, $end - $token->position)), implode('', \array_slice($characters, $end, $next - $end))];
        }

        $this->written = $written;
    }

    /**
     * The command in the library's notation.
     *
     * @throws InvalidExpression when the command writes what the library does not roll
     */
    public function rewrite(): string
    {
        while (!$this->peek()->is(TokenType::End)) {
            $this->step();
        }

        return $this->output;
    }

    /**
     * The offset in the command of a character of the output: the same character when it was copied, the start of the
     * token it replaces otherwise, the end of the command past the output.
     */
    public function position(int $offset): int
    {
        if ($offset >= $this->length) {
            return $this->count;
        }

        $position = 0;

        foreach ($this->pieces as [$start, $from, $copied]) {
            if ($start > $offset) {
                break;
            }

            $position = $offset - $start < $copied ? $from + $offset - $start : $from;
        }

        return $position;
    }

    private function step(): void
    {
        $token = $this->peek();

        if (($letters = $this->function()) > 0) {
            for ($index = 0; $index < $letters; ++$index) {
                $this->copy();
            }
        } elseif ($token->is(TokenType::Symbol, '$') && $this->tokens[$this->index + 1]->is(TokenType::Letter) && $this->tokens[$this->index + 1]->follows($token)) {
            $this->name();
        } elseif ($token->is(TokenType::Symbol, '[')) {
            $previous = $this->tokens[$this->index - 1] ?? null;
            $this->bracket($previous instanceof Token && \in_array($previous->text, self::JUDGES, true));
        } elseif ($token->is(TokenType::Letter)) {
            $this->letter($token);
        } else {
            $this->copy();
        }
    }

    /**
     * A letter of DiceParser: the operators the library writes otherwise are replaced, case by case.
     */
    private function letter(Token $token): void
    {
        $next = $this->tokens[$this->index + 1];

        match (true) {
            'K' === $token->text && $next->is(TokenType::Number) => $this->replace('!k'),
            'r' === $token->text => $this->replace('ro'),
            'R' === $token->text => $this->replace('r'),
            'e' === $token->text => $this->explosion(),
            's' === $token->text => $this->sort($next),
            'T' === $token->text && $next->is(TokenType::Symbol, '[') => $this->transforms(),
            'p' === $token->text && $next->is(TokenType::Symbol, '[') => $this->replace('look'),
            'o' === $token->text && $next->is(TokenType::Number) && $this->tokens[$this->index + 2]->is(TokenType::Symbol, ',') && $this->tokens[$this->index + 3]->is(TokenType::Number) => $this->occurrences(),
            'c' === $token->text && ($next->is(TokenType::Number) || $next->is(TokenType::Compare) || $next->is(TokenType::Symbol, '!') || $next->is(TokenType::Symbol, '-')) => $this->count(),
            'y' === $token->text => throw InvalidExpression::at($this->input, $token->position, 'The split of explosions "y" is not read'),
            ('m' === $token->text || 'b' === $token->text) && !$next->is(TokenType::Symbol, '[') => throw InvalidExpression::at($this->input, $token->position, \sprintf('"%s" is read with the instructions it takes: %1$s[1,2]', $token->text)),
            default => $this->copy(),
        };
    }

    /**
     * The number of letters of the function whose name comes next, followed by a parenthesis; 0 when none does.
     */
    private function function(): int
    {
        $word = '';
        $index = $this->index;

        while ($this->tokens[$index]->is(TokenType::Letter) && ($index === $this->index || $this->tokens[$index]->follows($this->tokens[$index - 1]))) {
            $word .= strtolower($this->tokens[$index++]->text);
        }

        return \in_array($word, self::FUNCTIONS, true) && $this->tokens[$index]->is(TokenType::Symbol, '(') ? $index - $this->index : 0;
    }

    /**
     * The name of a macro after its `$`: the letters and digits written against it, copied.
     */
    private function name(): void
    {
        $this->copy();

        do {
            $token = $this->peek();
            $this->copy();
            $next = $this->peek();
        } while ($next->follows($token) && ($next->is(TokenType::Letter) || $next->is(TokenType::Number)));
    }

    /**
     * What is written between brackets, copied up to the bracket that closes them; a validator's scope written in the
     * library's words.
     */
    private function bracket(bool $judged): void
    {
        $this->copy();

        if ($judged) {
            $this->scope();
        }

        $this->through(']');
    }

    /**
     * The scope a validator writes first, in the library's words.
     */
    private function scope(): void
    {
        $token = $this->peek();

        if (($token->is(TokenType::Symbol) || $token->is(TokenType::Other)) && \array_key_exists($token->text, self::SCOPES)) {
            $this->replace(self::SCOPES[$token->text]);
        }
    }

    /**
     * Copies — or steps over — the tokens up to the one that closes what was opened before them, which comes next then,
     * or up to the end.
     */
    private function inside(string $close, bool $write = true): void
    {
        $open = ']' === $close ? '[' : '(';
        $depth = 1;

        while (!$this->peek()->is(TokenType::End)) {
            $token = $this->peek();
            $depth += match (true) {
                $token->is(TokenType::Symbol, $open) => 1,
                $token->is(TokenType::Symbol, $close) => -1,
                default => 0,
            };

            if (0 === $depth) {
                return;
            }

            $write ? $this->copy() : ++$this->index;
        }
    }

    /**
     * Copies — or steps over — the tokens up to the one that closes what was opened before them, that one included.
     */
    private function through(string $close, bool $write = true): void
    {
        $this->inside($close, $write);

        if (!$this->peek()->is(TokenType::End)) {
            $write ? $this->copy() : ++$this->index;
        }
    }

    /**
     * `e`, `e10`, `e[>8]` and `e(3)[10]`: the explosion, its threshold, then its limit.
     */
    private function explosion(): void
    {
        $this->replace('!');

        if (!$this->peek()->is(TokenType::Symbol, '(')) {
            return;
        }

        $limit = $this->index++;
        $this->through(')', false);
        $end = $this->index;

        if ($this->peek()->is(TokenType::Symbol, '[')) {
            $this->copy();
            $this->through(']');
        } elseif ($this->peek()->is(TokenType::Number)) {
            $this->copy();
        } elseif ($this->peek()->is(TokenType::Compare)) {
            $this->copy();
            $this->signed();
        }

        for ($index = $limit; $index < $end; ++$index) {
            $this->copyAt($index);
        }
    }

    /**
     * `s` sorts in descending order, `sl` in ascending order.
     */
    private function sort(Token $next): void
    {
        $ascending = 'l' === $next->text && $next->follows($this->peek());
        $this->replace($ascending ? 'sa' : 'sd');

        if ($ascending) {
            $this->replace('');
        }
    }

    /**
     * `T[>5]{15}[<2]{1}{0}`: each validator and the value it gives, then the value of every other die.
     */
    private function transforms(): void
    {
        $this->emit('t[', $this->next());
        $first = true;

        while ($this->peek()->is(TokenType::Symbol, '[')) {
            if (!$first) {
                $this->emit(',', $this->peek());
            }

            $this->next();
            $this->scope();
            $this->inside(']');
            $this->expect(']');
            $this->emit(':', $this->tokens[$this->index - 1]);
            $this->value();
            $first = false;
        }

        if ($this->peek()->is(TokenType::Symbol, '{')) {
            $this->emit(','.self::EVERY_VALUE.':', $this->peek());
            $this->value();
        }

        $this->emit(']', $this->tokens[$this->index - 1]);
        $this->spaces();
    }

    /**
     * A value between braces, written without them.
     */
    private function value(): void
    {
        $this->expect('{');

        if (!$this->signed()) {
            throw $this->unexpected($this->peek());
        }

        $this->expect('}');
    }

    /**
     * `o2,7`: sets of two dice or more, of a value of 7 or more.
     */
    private function occurrences(): void
    {
        $this->copy();
        $this->copy();
        $this->emit('[>=', $this->next());
        $height = $this->next();
        $this->emit($height->text.']', $height);
        $this->spaces();
    }

    /**
     * `c7`, `c-1`, `c>7`, `c!=1`: the count of the dice that match, its validator between brackets.
     */
    private function count(): void
    {
        $this->emit('c[', $this->next());

        if ($this->peek()->is(TokenType::Symbol, '!')) {
            $this->copy();
        }

        if ($this->peek()->is(TokenType::Compare)) {
            $this->copy();
        }

        if (!$this->signed()) {
            throw $this->unexpected($this->peek());
        }

        $this->emit(']', $this->tokens[$this->index - 1]);
    }

    /**
     * A number, maybe negative, copied; whether there was one.
     */
    private function signed(): bool
    {
        if ($this->peek()->is(TokenType::Symbol, '-')) {
            $this->copy();
        }

        if (!$this->peek()->is(TokenType::Number)) {
            return false;
        }

        $this->copy();

        return true;
    }

    /**
     * Steps over a token that the output does not write, which must be the one given.
     */
    private function expect(string $text): void
    {
        $token = $this->next();

        if (!$token->is(TokenType::Symbol, $text)) {
            throw $this->unexpected($token);
        }
    }

    private function unexpected(Token $token): InvalidExpression
    {
        return InvalidExpression::at($this->input, $token->position, TokenType::End === $token->type ? 'Unexpected end' : \sprintf('Unexpected "%s"', $token->text));
    }

    /**
     * Copies the next token as written, with the spaces after it.
     */
    private function copy(): void
    {
        $this->copyAt($this->index++);
    }

    /**
     * Copies a token as written, with the spaces after it, wherever it is.
     */
    private function copyAt(int $index): void
    {
        [$text, $spaces] = $this->written[$index];
        $this->append($text.$spaces, $this->tokens[$index]->position, mb_strlen($text.$spaces));
    }

    /**
     * Replaces the next token, keeping the spaces after it.
     */
    private function replace(string $text): void
    {
        $token = $this->peek();
        $this->append($text.$this->written[$this->index++][1], $token->position, 0);
    }

    /**
     * The spaces after the token just read, which the output wrote in another form.
     */
    private function spaces(): void
    {
        $this->append($this->written[$this->index - 1][1], $this->tokens[$this->index - 1]->position, 0);
    }

    /**
     * Writes a text in place of a token, without the spaces after it.
     */
    private function emit(string $text, Token $token): void
    {
        $this->append($text, $token->position, 0);
    }

    private function append(string $text, int $from, int $copied): void
    {
        $this->pieces[] = [$this->length, $from, $copied];
        $this->output .= $text;
        $this->length += mb_strlen($text);
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
