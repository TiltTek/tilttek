<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

use JsonException;
use Tilttek\DeckBuilder\Exception\InvalidDeck;

/**
 * The reading of a decoded array — a format or a deck from configuration, JSON or a form —: its keys, then each value
 * with its type, every fault an {@see InvalidDeck}.
 *
 * @internal
 */
final class ArrayInput
{
    /**
     * The longest JSON text read, in bytes.
     */
    public const int JSON_BYTES = 1_048_576;

    private function __construct()
    {
    }

    /**
     * @throws InvalidDeck when the text is longer than 1 MiB or is not JSON
     */
    public static function json(string $json, string $what): mixed
    {
        if (\strlen($json) > self::JSON_BYTES) {
            throw InvalidDeck::because(\sprintf('A %s in JSON has at most %d bytes.', $what, self::JSON_BYTES));
        }

        try {
            return json_decode($json, true, 16, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidDeck::because(\sprintf('A %s in JSON does not read: %s.', $what, $jsonException->getMessage()));
        }
    }

    /**
     * The array itself, once it holds every required key and no other key than the optional ones.
     *
     * @param list<string> $required
     * @param list<string> $optional
     *
     * @return array<string, mixed>
     *
     * @throws InvalidDeck when the value is not an array of those keys
     */
    public static function object(mixed $data, string $what, array $required, array $optional = []): array
    {
        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw InvalidDeck::because(\sprintf('A %s is an object of keys.', $what));
        }

        $object = [];

        foreach ($data as $key => $value) {
            if (!\in_array($key, $required, true) && !\in_array($key, $optional, true)) {
                throw InvalidDeck::because(\sprintf('A %s has no key "%s".', $what, substr((string) $key, 0, 64)));
            }

            $object[$key] = $value;
        }

        foreach ($required as $key) {
            if (!\array_key_exists($key, $object)) {
                throw InvalidDeck::because(\sprintf('A %s needs its "%s".', $what, $key));
            }
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @throws InvalidDeck when the value is not a string
     */
    public static function string(array $object, string $key, string $what): string
    {
        $value = $object[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidDeck::because(\sprintf('The "%s" of a %s is a string.', $key, $what));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @throws InvalidDeck when the value is present and not an integer
     */
    public static function int(array $object, string $key, string $what, ?int $default = null): int
    {
        $value = \array_key_exists($key, $object) ? $object[$key] : $default;

        if (!\is_int($value)) {
            throw InvalidDeck::because(\sprintf('The "%s" of a %s is an integer.', $key, $what));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @throws InvalidDeck when the value is present and neither an integer nor null
     */
    public static function nullableInt(array $object, string $key, string $what): ?int
    {
        $value = $object[$key] ?? null;

        if (null !== $value && !\is_int($value)) {
            throw InvalidDeck::because(\sprintf('The "%s" of a %s is an integer or null.', $key, $what));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @return list<mixed>
     *
     * @throws InvalidDeck when the value is present and not a list of at most `$max` entries
     */
    public static function list(array $object, string $key, string $what, int $max): array
    {
        $value = $object[$key] ?? [];

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidDeck::because(\sprintf('The "%s" of a %s is a list.', $key, $what));
        }

        if (\count($value) > $max) {
            throw InvalidDeck::because(\sprintf('A %s has at most %d "%s".', $what, $max, $key));
        }

        return $value;
    }

    /**
     * A list of strings.
     *
     * @param array<string, mixed> $object
     *
     * @return list<string>
     *
     * @throws InvalidDeck when the value is present and not a list of at most `$max` strings
     */
    public static function strings(array $object, string $key, string $what, int $max): array
    {
        $strings = [];

        foreach (self::list($object, $key, $what, $max) as $value) {
            if (!\is_string($value)) {
                throw InvalidDeck::because(\sprintf('The "%s" of a %s are strings.', $key, $what));
            }

            $strings[] = $value;
        }

        return $strings;
    }

    /**
     * A map of string keys to integers, such as card identifiers to quantities.
     *
     * @param array<string, mixed> $object
     *
     * @return array<array-key, int>
     *
     * @throws InvalidDeck when the value is present and not an object of at most `$max` integers
     */
    public static function counts(array $object, string $key, string $what, int $max): array
    {
        $value = $object[$key] ?? [];

        // An object whose keys are 0, 1, 2… decodes as a list: its keys are kept as the strings they were.
        if (!\is_array($value)) {
            throw InvalidDeck::because(\sprintf('The "%s" of a %s is an object of integers.', $key, $what));
        }

        if (\count($value) > $max) {
            throw InvalidDeck::because(\sprintf('A %s has at most %d "%s".', $what, $max, $key));
        }

        $counts = [];

        foreach ($value as $name => $count) {
            if (!\is_int($count)) {
                throw InvalidDeck::because(\sprintf('The "%s" of a %s is an object of integers.', $key, $what));
            }

            $counts[(string) $name] = $count;
        }

        return $counts;
    }
}
