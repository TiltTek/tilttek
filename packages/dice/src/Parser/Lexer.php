<?php

declare(strict_types=1);

namespace Tilttek\Dice\Parser;

use Tilttek\Dice\Exception\InvalidExpression;

/**
 * Reads an input character by character, without regular expressions: runs of digits, single letters, a custom die's
 * name between braces written against the letter of a die, comparison signs, texts between double quotes, comments
 * from `#` or `//` to the end of the line, and the signs `+ - * / ( ) , ; % ! ^ [ ] { } & | : @ $ .`. Spaces separate
 * tokens and are dropped; any other character is kept as such, for the parser to refuse wherever it is not text.
 */
final class Lexer
{
    private const string SYMBOLS = '+-*/(),;%!^[]{}&|:@$.';

    /**
     * @param string $die the letter of a die in the notation read, besides `d`: a brace written against it opens a name
     *
     * @return list<Token> the tokens, the last one being the end
     */
    public static function tokenize(string $input, string $die = 'd'): array
    {
        $characters = mb_str_split($input);
        $count = \count($characters);
        $tokens = [];
        $position = 0;

        while ($position < $count) {
            if (ctype_space($characters[$position])) {
                ++$position;

                continue;
            }

            [$tokens[], $position] = self::read($input, $characters, $position, $tokens, $die);
        }

        $tokens[] = new Token(TokenType::End, '', $count);

        return $tokens;
    }

    /**
     * The token that starts at this offset, and the offset after it.
     *
     * @param list<string> $characters
     * @param list<Token>  $tokens     the tokens read before it
     *
     * @return array{Token, int}
     */
    private static function read(string $input, array $characters, int $start, array $tokens, string $die): array
    {
        $character = $characters[$start];
        $position = $start + 1;

        if (ctype_digit($character)) {
            while (ctype_digit($characters[$position] ?? '')) {
                $character .= $characters[$position++];
            }

            return [new Token(TokenType::Number, $character, $start), $position];
        }

        if ('{' === $character && self::afterDie($tokens, $start, $die)) {
            $end = self::find($characters, '}', $position) ?? throw InvalidExpression::at($input, $start, 'Unclosed brace');

            return [new Token(TokenType::Name, self::slice($characters, $position, $end), $start), $end + 1];
        }

        if ('"' === $character) {
            $end = self::closingQuote($characters, $position) ?? throw InvalidExpression::at($input, $start, 'Unclosed quote');

            return [new Token(TokenType::Text, self::slice($characters, $position, $end), $start), $end + 1];
        }

        if ('#' === $character || ('/' === $character && '/' === ($characters[$position] ?? null))) {
            $from = '#' === $character ? $position : $position + 1;
            $end = self::find($characters, "\n", $from) ?? \count($characters);

            return [new Token(TokenType::Comment, trim(self::slice($characters, $from, $end)), $start), $end];
        }

        if ('<' === $character || '>' === $character || '=' === $character) {
            $compare = '=' !== $character && '=' === ($characters[$position] ?? null);

            return [new Token(TokenType::Compare, $compare ? $character.'=' : $character, $start), $compare ? $position + 1 : $position];
        }

        return [new Token(match (true) {
            ctype_alpha($character) => TokenType::Letter,
            str_contains(self::SYMBOLS, $character) => TokenType::Symbol,
            default => TokenType::Other,
        }, $character, $start), $position];
    }

    /**
     * Whether the last token read is the letter of a die, written against the brace at this position.
     *
     * @param list<Token> $tokens
     */
    private static function afterDie(array $tokens, int $position, string $die): bool
    {
        $last = $tokens[\count($tokens) - 1] ?? null;

        return $last instanceof Token && $last->position === $position - 1 && ($last->isLetter('d') || $last->isLetter(strtolower($die)));
    }

    /**
     * @param list<string> $characters
     */
    private static function find(array $characters, string $character, int $from): ?int
    {
        $end = array_search($character, \array_slice($characters, $from, preserve_keys: true), true);

        return false === $end ? null : $end;
    }

    /**
     * The offset of the quote that closes a text, past the characters a backslash escapes.
     *
     * @param list<string> $characters
     */
    private static function closingQuote(array $characters, int $from): ?int
    {
        $count = \count($characters);

        for ($position = $from; $position < $count; ++$position) {
            if ('"' === $characters[$position]) {
                return $position;
            }

            $position += '\\' === $characters[$position] ? 1 : 0;
        }

        return null;
    }

    /**
     * @param list<string> $characters
     */
    private static function slice(array $characters, int $from, int $to): string
    {
        return implode('', \array_slice($characters, $from, $to - $from));
    }
}
