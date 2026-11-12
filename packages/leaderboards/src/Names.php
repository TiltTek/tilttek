<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use Tilttek\Leaderboards\Exception\InvalidLeaderboard;

/**
 * The names the application gives its boards and seasons: 1 to 64 lower-case letters, digits, "_", "." or "-",
 * starting with a letter or a digit.
 */
final class Names
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidLeaderboard
     */
    public static function check(string $name, string $what): string
    {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $name)) {
            throw InvalidLeaderboard::because(\sprintf('"%s" is not a %s name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit.', $name, $what));
        }

        return $name;
    }
}
