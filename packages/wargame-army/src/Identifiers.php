<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Exception\InvalidArmyList;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;

/**
 * The shapes the library accepts: the code of a catalogue, a unit, an option, a group, a detachment type or a
 * category; the version of a catalogue; the identifier of an army list; and a name shown to people.
 */
final class Identifiers
{
    /**
     * A code: 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter or a digit.
     */
    public const string CODE = '/^[a-z0-9][a-z0-9_.-]{0,63}$/D';

    /**
     * The stable identifier of an army list: 1 to 255 ASCII letters, digits, `_`, `.`, `:`, `/` or `-`, starting with a
     * letter or a digit.
     */
    public const string ID = '/^[A-Za-z0-9][A-Za-z0-9_.:\/-]{0,254}$/D';

    /**
     * The version of a catalogue: 1 to 32 ASCII letters, digits, `_`, `.`, `+` or `-`, starting with a letter or a
     * digit — `2026.1`, `3.0-beta`.
     */
    public const string VERSION = '/^[A-Za-z0-9][A-Za-z0-9_.+-]{0,31}$/D';

    /**
     * The longest name, in characters.
     */
    public const int NAME_LENGTH = 200;

    private function __construct()
    {
    }

    /**
     * @throws InvalidCatalogue|InvalidArmyList when the code does not have the shape of {@see self::CODE}
     */
    public static function code(string $code, string $what, Fault $fault = Fault::Catalogue): string
    {
        if (1 !== preg_match(self::CODE, $code)) {
            throw $fault->because(\sprintf('The code of %s is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "%s" is not.', self::a($what), self::excerpt($code)));
        }

        return $code;
    }

    /**
     * @throws InvalidArmyList when the identifier does not have the shape of {@see self::ID}
     */
    public static function id(string $id): string
    {
        if (1 !== preg_match(self::ID, $id)) {
            throw InvalidArmyList::because(\sprintf('The identifier of an army list is 1 to 255 letters, digits, "_", ".", ":", "/" or "-", starting with a letter or a digit; "%s" is not.', self::excerpt($id)));
        }

        return $id;
    }

    /**
     * @throws InvalidCatalogue|InvalidArmyList when the version does not have the shape of {@see self::VERSION}
     */
    public static function version(string $version, Fault $fault = Fault::Catalogue): string
    {
        if (1 !== preg_match(self::VERSION, $version)) {
            throw $fault->because(\sprintf('The version of a catalogue is 1 to 32 letters, digits, "_", ".", "+" or "-", starting with a letter or a digit; "%s" is not.', self::excerpt($version)));
        }

        return $version;
    }

    /**
     * A name: 1 to 200 characters of valid UTF-8, without control characters or surrounding spaces.
     *
     * @throws InvalidCatalogue|InvalidArmyList when the name is empty, too long, not UTF-8 or holds a control character
     */
    public static function name(string $name, string $what, Fault $fault = Fault::Catalogue): string
    {
        if (1 !== preg_match('/^[^\p{Cc}]+$/Du', $name) || trim($name) !== $name) {
            throw $fault->because(\sprintf('The name of %s is text without control characters or surrounding spaces.', self::a($what)));
        }

        if (1 !== preg_match('/^.{1,'.self::NAME_LENGTH.'}$/Dsu', $name)) {
            throw $fault->because(\sprintf('The name of %s has at most %d characters.', self::a($what), self::NAME_LENGTH));
        }

        return $name;
    }

    /**
     * A whole number within bounds. The message names the number by `$what`, whose `%s` takes `$subject`, written only
     * when the number is refused.
     *
     * @throws InvalidCatalogue|InvalidArmyList when the number is out of its bounds
     */
    public static function bounded(int $value, int $min, int $max, string $what, string $subject = '', Fault $fault = Fault::Catalogue): int
    {
        if ($value < $min || $value > $max) {
            throw $fault->because(\sprintf('%s is from %d to %d; %d is not.', ucfirst(str_replace('%s', $subject, $what)), $min, $max, $value));
        }

        return $value;
    }

    /**
     * A noun with its article, as messages show it: "a unit", "an option".
     *
     * @internal
     */
    public static function a(string $what): string
    {
        return (1 === preg_match('/^[aeio]/', $what) ? 'an ' : 'a ').$what;
    }

    /**
     * The key of a PHP array as the string it was given as: an identifier of digits, such as "7", is an integer key.
     *
     * @internal
     */
    public static function key(int|string $key): string
    {
        return (string) $key;
    }

    /**
     * The start of a refused value, short enough for a message.
     */
    private static function excerpt(string $value): string
    {
        return substr($value, 0, 64);
    }
}
