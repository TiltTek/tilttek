<?php

declare(strict_types=1);

namespace Tilttek\Dice\Parser;

/**
 * The kinds of token the lexer reads: digits, one letter, a custom die's name between braces, a comparison sign, any
 * other sign, a text between double quotes, a comment, a character the notation does not use, and the end of the
 * input.
 */
enum TokenType
{
    case Number;
    case Letter;
    case Name;
    case Compare;
    case Symbol;
    case Text;
    case Comment;
    case Other;
    case End;
}
