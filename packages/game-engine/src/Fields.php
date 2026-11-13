<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * The fields of the plain values a log or a state is read back from, each checked for its type, so that what a store
 * or a cache gives back is refused with `InvalidGame` rather than trusted.
 *
 * @internal
 */
final class Fields
{
    private function __construct()
    {
    }

    /**
     * @param array<mixed> $data
     *
     * @throws InvalidGame
     */
    public static function text(array $data, string $name, string $of): string
    {
        $value = $data[$name] ?? null;

        return \is_string($value) ? $value : throw InvalidGame::because(\sprintf('The field "%s" of %s is not a text.', $name, $of));
    }

    /**
     * A whole number of 0 or more.
     *
     * @param array<mixed> $data
     *
     * @throws InvalidGame
     */
    public static function count(array $data, string $name, string $of): int
    {
        $value = $data[$name] ?? null;

        return \is_int($value) && $value >= 0 ? $value : throw InvalidGame::because(\sprintf('The field "%s" of %s is not a whole number of 0 or more.', $name, $of));
    }

    /**
     * @param array<mixed> $data
     *
     * @return list<mixed>
     *
     * @throws InvalidGame
     */
    public static function list(array $data, string $name, string $of): array
    {
        $value = $data[$name] ?? [];

        return \is_array($value) && array_is_list($value) ? $value : throw InvalidGame::because(\sprintf('The field "%s" of %s is not a list.', $name, $of));
    }

    /**
     * A list of texts.
     *
     * @param array<mixed> $data
     *
     * @return list<string>
     *
     * @throws InvalidGame
     */
    public static function texts(array $data, string $name, string $of): array
    {
        return array_map(static fn (mixed $text): string => \is_string($text) ? $text : throw InvalidGame::because(\sprintf('An element of the field "%s" of %s is not a text.', $name, $of)), self::list($data, $name, $of));
    }

    /**
     * A mapping by name; an empty list is an empty mapping, as JSON writes it.
     *
     * @param array<mixed> $data
     *
     * @return array<string, mixed>
     *
     * @throws InvalidGame
     */
    public static function map(array $data, string $name, string $of): array
    {
        $value = $data[$name] ?? [];
        $map = [];

        if (!\is_array($value) || ([] !== $value && array_is_list($value))) {
            throw InvalidGame::because(\sprintf('The field "%s" of %s is not a mapping.', $name, $of));
        }

        foreach ($value as $key => $item) {
            $map[(string) $key] = $item;
        }

        return $map;
    }

    /**
     * A mapping of whole numbers by name.
     *
     * @param array<mixed> $data
     *
     * @return array<string, int>
     *
     * @throws InvalidGame
     */
    public static function numbers(array $data, string $name, string $of): array
    {
        return array_map(static fn (mixed $number): int => \is_int($number) ? $number : throw InvalidGame::because(\sprintf('An element of the field "%s" of %s is not a whole number.', $name, $of)), self::map($data, $name, $of));
    }
}
