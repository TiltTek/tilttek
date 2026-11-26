<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Game;

use Tilttek\MiniGames\Exception\InvalidMiniGame;

/**
 * A code of Bulls and Cows: 3 to 5 different digits, `0` included, and how a guess scores against it.
 */
final readonly class Code
{
    public const int MIN_LENGTH = 3;

    public const int MAX_LENGTH = 5;

    private function __construct(
        public string $digits,
    ) {
    }

    /**
     * @throws InvalidMiniGame when the text is not `$length` different digits
     */
    public static function read(string $text, int $length): self
    {
        if ($length < self::MIN_LENGTH || $length > self::MAX_LENGTH) {
            throw InvalidMiniGame::because(\sprintf('A code has %d to %d digits.', self::MIN_LENGTH, self::MAX_LENGTH));
        }

        if (1 !== preg_match('/^[0-9]{'.$length.'}$/D', $text) || \count(array_unique(str_split($text))) !== $length) {
            throw InvalidMiniGame::because(\sprintf('A guess is %d different digits.', $length));
        }

        return new self($text);
    }

    /**
     * The digits of the guess in their right place — the bulls —, then the digits of the code in another place — the
     * cows.
     *
     * @return array{int, int}
     */
    public function score(self $guess): array
    {
        $bulls = 0;
        $common = 0;

        foreach (str_split($guess->digits) as $place => $digit) {
            $bulls += $this->digits[$place] === $digit ? 1 : 0;
            $common += str_contains($this->digits, $digit) ? 1 : 0;
        }

        return [$bulls, $common - $bulls];
    }
}
