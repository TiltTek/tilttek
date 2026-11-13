<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder;

use Tilttek\PuzzleBuilder\Exception\InvalidScenario;

/**
 * One tier of the hints of a puzzle: the tiers are revealed in their order, the first a nudge, the last close to the
 * answer.
 */
final readonly class Hint
{
    public const int MAX_TEXT = 2_000;

    /** The longest penalty, in seconds: one day. */
    public const int MAX_PENALTY = 86_400;

    /**
     * @param int $penalty what revealing it costs, in seconds; what a game does with it is its own: an escape room adds
     *                     it to the final time
     *
     * @throws InvalidScenario when the text is empty or longer than 2 000 characters, or the penalty is out of bounds
     */
    public function __construct(
        public string $text,
        public int $penalty = 0,
    ) {
        if ('' === trim($text) || mb_strlen($text, 'UTF-8') > self::MAX_TEXT) {
            throw InvalidScenario::because(\sprintf('A hint holds 1 to %d characters.', self::MAX_TEXT));
        }

        if ($penalty < 0 || $penalty > self::MAX_PENALTY) {
            throw InvalidScenario::because(\sprintf('The penalty of a hint is 0 to %d seconds.', self::MAX_PENALTY));
        }
    }
}
