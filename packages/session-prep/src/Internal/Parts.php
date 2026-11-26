<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Internal;

use Tilttek\SessionPrep\Exception\InvalidPreparation;
use Tilttek\SessionPrep\Part;

/**
 * Lists of parts kept in the order the game master gives them, each found by its key.
 *
 * @internal
 */
final class Parts
{
    private function __construct()
    {
    }

    /**
     * The parts, at most so many, each key once.
     *
     * @template T of Part
     *
     * @param list<T> $parts
     * @param string  $what  "scenes", "secrets"…
     *
     * @return list<T>
     *
     * @throws InvalidPreparation when there are too many, or a key comes twice
     */
    public static function distinct(array $parts, int $max, string $what): array
    {
        if (\count($parts) > $max) {
            throw InvalidPreparation::because(\sprintf('A preparation holds at most %d %s.', $max, $what));
        }

        $keys = array_map(static fn (Part $part): string => $part->key, $parts);
        $twice = array_values(array_diff_key($keys, array_unique($keys)));

        if ([] !== $twice) {
            throw InvalidPreparation::because(\sprintf('The key %s comes twice among the %s.', $twice[0], $what));
        }

        return $parts;
    }

    /**
     * @template T of Part
     *
     * @param list<T> $parts
     *
     * @return ?T
     */
    public static function find(array $parts, string $key): ?Part
    {
        foreach ($parts as $part) {
            if ($part->key === $key) {
                return $part;
            }
        }

        return null;
    }

    /**
     * The part of the key, or an exception.
     *
     * @template T of Part
     *
     * @param list<T> $parts
     * @param string  $what  "scene", "secret"…
     *
     * @return T
     *
     * @throws InvalidPreparation when no part has the key
     */
    public static function get(array $parts, string $key, string $what): Part
    {
        return self::find($parts, $key) ?? throw InvalidPreparation::because(\sprintf('No %s has the key %s.', $what, $key));
    }

    /**
     * The parts with this one in place of the part of its key, or after the others when none has it.
     *
     * @template T of Part
     *
     * @param list<T> $parts
     * @param T       $part
     *
     * @return list<T>
     */
    public static function put(array $parts, Part $part): array
    {
        foreach ($parts as $index => $kept) {
            if ($kept->key === $part->key) {
                $parts[$index] = $part;

                return $parts;
            }
        }

        $parts[] = $part;

        return $parts;
    }

    /**
     * The parts without the part of the key.
     *
     * @template T of Part
     *
     * @param list<T> $parts
     *
     * @return list<T>
     *
     * @throws InvalidPreparation when no part has the key
     */
    public static function remove(array $parts, string $key, string $what): array
    {
        self::get($parts, $key, $what);

        return array_values(array_filter($parts, static fn (Part $part): bool => $part->key !== $key));
    }

    /**
     * The parts with the part of the key moved just before another, or after every other when there is none.
     *
     * @template T of Part
     *
     * @param list<T> $parts
     *
     * @return list<T>
     *
     * @throws InvalidPreparation when no part has a key, or a part is to move before itself
     */
    public static function move(array $parts, string $key, ?string $before, string $what): array
    {
        $moved = self::get($parts, $key, $what);

        if ($before === $key) {
            throw InvalidPreparation::because(\sprintf('The %s %s cannot move before itself.', $what, $key));
        }

        if (null !== $before) {
            self::get($parts, $before, $what);
        }

        $order = [];

        foreach ($parts as $part) {
            if ($part->key === $before) {
                $order[] = $moved;
            }

            if ($part->key !== $key) {
                $order[] = $part;
            }
        }

        if (null === $before) {
            $order[] = $moved;
        }

        return $order;
    }
}
