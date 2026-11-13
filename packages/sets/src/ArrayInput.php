<?php

declare(strict_types=1);

namespace Tilttek\Sets;

use Tilttek\Sets\Exception\InvalidSet;

/**
 * The reading of a decoded array — a definition from configuration, JSON or a form —: its keys, then each value with
 * its type, every fault an {@see InvalidSet}.
 *
 * @internal
 */
final class ArrayInput
{
    private function __construct()
    {
    }

    /**
     * The array itself, once it holds every required key and no other key than the optional ones.
     *
     * @param list<string> $required
     * @param list<string> $optional
     *
     * @return array<string, mixed>
     *
     * @throws InvalidSet when the value is not an array of those keys
     */
    public static function object(mixed $data, string $what, array $required, array $optional = []): array
    {
        if (!\is_array($data) || ([] !== $data && array_is_list($data))) {
            throw InvalidSet::because(\sprintf('A %s is an object of keys.', $what));
        }

        $object = [];

        foreach ($data as $key => $value) {
            if (!\in_array($key, $required, true) && !\in_array($key, $optional, true)) {
                throw InvalidSet::because(\sprintf('A %s has no key "%s".', $what, substr((string) $key, 0, 64)));
            }

            $object[$key] = $value;
        }

        foreach ($required as $key) {
            if (!\array_key_exists($key, $object)) {
                throw InvalidSet::because(\sprintf('A %s needs its "%s".', $what, $key));
            }
        }

        return $object;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @throws InvalidSet when the value is not a string
     */
    public static function string(array $object, string $key, string $what): string
    {
        $value = $object[$key] ?? null;

        if (!\is_string($value)) {
            throw InvalidSet::because(\sprintf('The "%s" of a %s is a string.', $key, $what));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @throws InvalidSet when the value is present and not an integer
     */
    public static function int(array $object, string $key, string $what, ?int $default = null): int
    {
        $value = \array_key_exists($key, $object) ? $object[$key] : $default;

        if (!\is_int($value)) {
            throw InvalidSet::because(\sprintf('The "%s" of a %s is an integer.', $key, $what));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $object
     *
     * @return list<mixed>
     *
     * @throws InvalidSet when the value is present and not a list of at most `$max` entries
     */
    public static function list(array $object, string $key, string $what, int $max): array
    {
        $value = \array_key_exists($key, $object) ? $object[$key] : [];

        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidSet::because(\sprintf('The "%s" of a %s is a list.', $key, $what));
        }

        if (\count($value) > $max) {
            throw InvalidSet::because(\sprintf('A %s has at most %d "%s".', $what, $max, $key));
        }

        return $value;
    }
}
