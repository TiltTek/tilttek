<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Tilttek\Collections\Exception\InvalidCollection;

/**
 * The codes of rarities, items, sets, packs and reward kinds: 1 to 64 lower-case letters, digits, "_", "." or "-",
 * starting with a letter, such as `rare`, `chibi-pirate` or `dice.ember`.
 */
final class Names
{
    private function __construct()
    {
    }

    public static function check(string $name, string $what): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_.-]{0,63}$/D', $name)) {
            throw InvalidCollection::name($what, $name);
        }

        return $name;
    }
}
