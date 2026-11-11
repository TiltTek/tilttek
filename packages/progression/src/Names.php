<?php

declare(strict_types=1);

namespace Tilttek\Progression;

use Tilttek\Progression\Exception\InvalidProgression;

/**
 * The names the application gives its tracks, rules, milestones, seasons and streaks: 1 to 64 lower-case letters,
 * digits, "_", "." or "-", starting with a letter.
 */
final class Names
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidProgression
     */
    public static function check(string $name, string $what): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name)) {
            throw InvalidProgression::name($what, $name);
        }

        return $name;
    }
}
