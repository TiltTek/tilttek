<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

use Tilttek\Quiz\Exception\InvalidQuiz;

/**
 * The PIN of a live session, as a player types it: six digits, which may be split by spaces or hyphens ("123 456",
 * "123-456"). What a player types is read once, here, before any store is asked: anything longer than `MAX_INPUT`
 * bytes, or that is not six ASCII digits once the separators are left out, is refused.
 */
final class Pin
{
    /** The longest input read, in bytes: six digits and room for the separators a player types. */
    public const int MAX_INPUT = 16;

    private function __construct()
    {
    }

    /**
     * The six digits of the PIN typed.
     *
     * @throws InvalidQuiz when the input is not a PIN
     */
    public static function parse(string $input): string
    {
        if (\strlen($input) > self::MAX_INPUT) {
            throw InvalidQuiz::because(\sprintf('A PIN is six digits; at most %d bytes are read.', self::MAX_INPUT));
        }

        $pin = str_replace([' ', '-'], '', trim($input));

        if (1 !== preg_match('/^\d{6}$/', $pin)) {
            throw InvalidQuiz::because('A PIN is six digits, which spaces or hyphens may split.');
        }

        return $pin;
    }
}
