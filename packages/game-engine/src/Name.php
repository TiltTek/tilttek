<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * The checks of the names the library reads: games, phases, transitions, options, seats, identifiers and move types.
 *
 * @internal
 */
final class Name
{
    private const string NAME = '/^[a-z][a-z0-9]*(?:[-_][a-z0-9]+)*$/D';

    private const string TYPE = '/^[a-z][a-z0-9-]*(?:\.[a-z][a-z0-9-]*)*$/D';

    private const string IDENTIFIER = '/^[A-Za-z][A-Za-z0-9:._-]*$/D';

    /**
     * A name: lowercase ASCII letters and digits, words joined by `-` or `_`, 64 characters at most.
     *
     * @throws InvalidGame
     */
    public static function check(string $name, string $what): string
    {
        if (\strlen($name) > 64 || 1 !== preg_match(self::NAME, $name)) {
            throw InvalidGame::because(\sprintf('The name of %s "%s" must be 1 to 64 lowercase letters and digits, words joined by "-" or "_".', $what, $name));
        }

        return $name;
    }

    /**
     * A move type: names joined by dots, `race.roll`, 64 characters at most.
     *
     * @throws InvalidGame
     */
    public static function type(string $type): string
    {
        if (\strlen($type) > 64 || 1 !== preg_match(self::TYPE, $type)) {
            throw InvalidGame::because(\sprintf('The move type "%s" must be 1 to 64 lowercase letters, digits and "-", in names joined by dots.', $type));
        }

        return $type;
    }

    /**
     * An identifier the application gives — a table, a seat —: an ASCII letter, then ASCII letters, digits, `:`, `.`,
     * `_` and `-`, 128 characters at most: `table:42`, `member:7`. It never reads as a number, so that it stays a text
     * as a key of a JSON mapping.
     *
     * @throws InvalidGame
     */
    public static function identifier(string $identifier, string $what): string
    {
        if (\strlen($identifier) > 128 || 1 !== preg_match(self::IDENTIFIER, $identifier)) {
            throw InvalidGame::because(\sprintf('The identifier of %s "%s" must be an ASCII letter followed by up to 127 ASCII letters, digits, ":", ".", "_" or "-".', $what, $identifier));
        }

        return $identifier;
    }
}
