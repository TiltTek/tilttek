<?php

declare(strict_types=1);

namespace Tilttek\Dice\Parser;

/**
 * A token and the offset, in characters, where it starts.
 */
final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $text,
        public int $position,
    ) {
    }

    public function is(TokenType $type, ?string $text = null): bool
    {
        return $type === $this->type && (null === $text || $text === $this->text);
    }

    /**
     * Whether this token starts where the one given ends, with no space between them; the tokens it is asked of —
     * letters, digits and signs — are written in ASCII.
     */
    public function follows(self $token): bool
    {
        return $this->position === $token->position + \strlen($token->text);
    }

    /**
     * Whether this token is the letter given, in either case.
     */
    public function isLetter(string $letter): bool
    {
        return TokenType::Letter === $this->type && strtolower($this->text) === $letter;
    }
}
