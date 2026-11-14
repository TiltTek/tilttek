<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

use Tilttek\Quiz\Exception\InvalidQuiz;

/**
 * Who plays: a member, by the opaque reference the application gives, or a guest of a live session, by an identifier
 * the application draws and the pseudonym the guest chose. A guest has no account: once the session is over, the
 * application offers them to become a member, and their score follows them if they do.
 */
final readonly class Player
{
    /** The longest pseudonym, in characters. */
    public const int MAX_PSEUDONYM = 24;

    private function __construct(
        public string $id,
        public ?string $pseudonym,
    ) {
    }

    public static function member(string $id): self
    {
        return new self(Identifier::check($id, 'player'), null);
    }

    /**
     * A guest, under a pseudonym of 1 to `MAX_PSEUDONYM` characters, trimmed, without control or invisible characters.
     */
    public static function guest(string $id, string $pseudonym): self
    {
        $pseudonym = trim($pseudonym);

        if (!mb_check_encoding($pseudonym, 'UTF-8') || 1 === preg_match('/\p{C}/u', $pseudonym)) {
            throw InvalidQuiz::because('A pseudonym is text, without control or invisible characters.');
        }

        $length = mb_strlen($pseudonym, 'UTF-8');

        if ($length < 1 || $length > self::MAX_PSEUDONYM) {
            throw InvalidQuiz::because(\sprintf('A pseudonym is 1 to %d characters; this one is %d.', self::MAX_PSEUDONYM, $length));
        }

        return new self(Identifier::check($id, 'player'), $pseudonym);
    }

    public function isGuest(): bool
    {
        return null !== $this->pseudonym;
    }
}
