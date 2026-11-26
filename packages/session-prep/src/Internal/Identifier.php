<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Internal;

use Tilttek\SessionPrep\Exception\InvalidPreparation;

/**
 * The shape of every identifier and key the library takes. An identifier is stable and opaque, never a name or an
 * address: it holds no "@". It starts with a letter, so that PHP never reads it as an integer key. A key names a part
 * within one preparation, and is shorter.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_LENGTH = 128;

    public const int MAX_KEY_LENGTH = 64;

    private const string IDENTIFIER = '/^[A-Za-z][A-Za-z0-9_.:-]{0,127}$/D';

    private const string KEY = '/^[A-Za-z][A-Za-z0-9_.:-]{0,63}$/D';

    private function __construct()
    {
    }

    /**
     * @param string $what "a preparation", "a campaign", "a member", "a session" or "an entry"
     *
     * @throws InvalidPreparation when the value is not an identifier
     */
    public static function check(string $value, string $what): string
    {
        if (1 !== preg_match(self::IDENTIFIER, $value)) {
            throw InvalidPreparation::because(\sprintf('The identifier of %s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter.', $what, self::MAX_LENGTH));
        }

        return $value;
    }

    /**
     * @param string $what "a scene", "a secret", "a clue"…
     *
     * @throws InvalidPreparation when the value is not a key
     */
    public static function key(string $value, string $what): string
    {
        if (1 !== preg_match(self::KEY, $value)) {
            throw InvalidPreparation::because(\sprintf('The key of %s is 1 to %d letters, digits, "_", ".", ":" or "-", starting with a letter.', $what, self::MAX_KEY_LENGTH));
        }

        return $value;
    }

    /**
     * Distinct keys, in their order, at most so many.
     *
     * @param list<string> $keys
     *
     * @return list<string>
     *
     * @throws InvalidPreparation when one is not a key, one comes twice, or there are too many
     */
    public static function keys(array $keys, int $max, string $what): array
    {
        if (\count($keys) > $max) {
            throw InvalidPreparation::because(\sprintf('%s names at most %d.', $what, $max));
        }

        if (\count(array_unique(array_map(static fn (string $key): string => self::key($key, 'a part'), $keys))) !== \count($keys)) {
            throw InvalidPreparation::because(\sprintf('%s names a key twice.', $what));
        }

        return $keys;
    }
}
