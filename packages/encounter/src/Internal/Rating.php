<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Internal;

use Tilttek\Encounter\Exception\InvalidEncounter;

/**
 * The shape of a rating, as a game system writes a level or a challenge: `3`, `1/4`, `-1`, `12+`, `A`.
 *
 * @internal
 */
final class Rating
{
    public const int MAX_LENGTH = 16;

    private const string SHAPE = '/^[A-Za-z0-9\/.+-]{1,16}$/D';

    private function __construct()
    {
    }

    /**
     * @param int|string $value a rating, or an integer key PHP made of one
     * @param string     $what  "the rating of the participant goblins"...
     *
     * @throws InvalidEncounter when the value is not a rating
     */
    public static function check(int|string $value, string $what): string
    {
        $value = (string) $value;
        if (1 !== preg_match(self::SHAPE, $value)) {
            throw InvalidEncounter::because(\sprintf('%s is 1 to %d letters, digits, "/", ".", "+" or "-".', ucfirst($what), self::MAX_LENGTH));
        }

        return $value;
    }
}
