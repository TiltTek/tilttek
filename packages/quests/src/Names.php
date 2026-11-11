<?php

declare(strict_types=1);

namespace Tilttek\Quests;

use Tilttek\Quests\Exception\InvalidQuest;

/**
 * The codes the application gives its quests, their steps and the kinds of their rewards: 1 to 64 lower-case letters,
 * digits, "_", "." or "-", starting with a letter.
 */
final class Names
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidQuest
     */
    public static function check(string $name, string $what): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name)) {
            throw InvalidQuest::name($what, $name);
        }

        return $name;
    }
}
