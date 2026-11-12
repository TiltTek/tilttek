<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use Tilttek\Leaderboards\Exception\InvalidLeaderboard;

/**
 * A member's choice of how the others see them on the boards, and the pseudonym that goes with it: 1 to 40
 * characters, no control character, no space at either end.
 */
final readonly class Appearance
{
    public const int MAX_PSEUDONYM = 40;

    public function __construct(
        public Visibility $visibility = Visibility::Hidden,
        public ?string $pseudonym = null,
    ) {
        if ((Visibility::Pseudonym === $visibility) === (null === $pseudonym)) {
            throw InvalidLeaderboard::because('A member who appears under a pseudonym chooses one, and only then.');
        }

        if (null !== $pseudonym && 1 !== preg_match('/^[^\p{C}\s](?:[^\p{C}]{0,38}[^\p{C}\s])?$/uD', $pseudonym)) {
            throw InvalidLeaderboard::because(\sprintf('"%s" is not a pseudonym: 1 to %d characters, no control character, no space at either end.', $pseudonym, self::MAX_PSEUDONYM));
        }
    }

    public static function hidden(): self
    {
        return new self();
    }

    public static function named(): self
    {
        return new self(Visibility::Named);
    }

    public static function pseudonym(string $pseudonym): self
    {
        return new self(Visibility::Pseudonym, $pseudonym);
    }
}
