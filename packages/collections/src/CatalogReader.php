<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Tilttek\Collections\Exception\InvalidCollection;

/**
 * A catalog from plain values, as a configuration or a database holds them:
 *
 * ```php
 * [
 *     'rarities' => ['common' => 70, 'rare' => 25, 'legendary' => 5],
 *     'items' => [
 *         'pirate' => ['name' => ['en' => 'Pirate', 'fr' => 'Pirate'], 'rarity' => 'rare', 'sets' => ['crew'], 'image' => '/img/pirate.webp'],
 *         'ember-d6' => ['name' => 'Ember d6', 'rarity' => 'common', 'sets' => ['ember'], 'die' => ['sides' => 6, 'color' => '#c0392b']],
 *     ],
 *     'sets' => [
 *         'crew' => ['name' => 'The crew', 'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50]]],
 *         'ember' => ['name' => 'Ember dice', 'kind' => 'base'],
 *     ],
 *     'packs' => ['booster' => ['name' => 'Booster', 'size' => 5, 'weights' => ['common' => 80, 'rare' => 18, 'legendary' => 2]]],
 * ]
 * ```
 *
 * A rarity is its weight, or `{weight}`. Unknown keys are refused, so that a typo never passes for a default.
 */
final class CatalogReader
{
    private const array ITEM_KEYS = ['name', 'rarity', 'sets', 'description', 'image', 'tradable', 'die'];

    private const array DIE_KEYS = ['sides', 'color', 'material'];

    private const array SET_KEYS = ['name', 'kind', 'rewards'];

    private const array REWARD_KEYS = ['kind', 'code', 'amount'];

    private const array PACK_KEYS = ['name', 'size', 'sets', 'weights'];

    private function __construct()
    {
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    public static function read(array $definition): Catalog
    {
        self::keys($definition, ['rarities', 'items', 'sets', 'packs'], 'the catalog');

        return new Catalog(
            self::each($definition, 'rarities', self::rarity(...)),
            self::each($definition, 'items', self::item(...)),
            self::each($definition, 'sets', self::set(...)),
            self::each($definition, 'packs', self::pack(...)),
        );
    }

    private static function rarity(string $code, mixed $definition): Rarity
    {
        $weight = \is_array($definition) ? self::keys($definition, ['weight'], \sprintf('the rarity "%s"', $code))['weight'] ?? null : $definition;

        if (!\is_int($weight)) {
            throw InvalidCollection::because(\sprintf('The rarity "%s" has a whole weight.', $code));
        }

        return new Rarity($code, $weight);
    }

    private static function item(string $code, mixed $definition): Item
    {
        $definition = self::keys(self::map($definition, \sprintf('The item "%s"', $code)), self::ITEM_KEYS, \sprintf('the item "%s"', $code));
        $die = $definition['die'] ?? null;
        $tradable = $definition['tradable'] ?? true;

        if (!\is_bool($tradable)) {
            throw InvalidCollection::because(\sprintf('Whether "%s" changes hands is true or false.', $code));
        }

        return new Item(
            $code,
            self::texts($definition['name'] ?? null, \sprintf('The name of "%s"', $code)),
            self::string($definition['rarity'] ?? null, \sprintf('The rarity of "%s"', $code)),
            self::strings($definition['sets'] ?? [], \sprintf('The sets of "%s"', $code)),
            null === ($definition['description'] ?? null) ? null : self::texts($definition['description'], \sprintf('The description of "%s"', $code)),
            null === ($definition['image'] ?? null) ? null : self::string($definition['image'], \sprintf('The image of "%s"', $code)),
            $tradable,
            null === $die ? null : self::die($code, $die),
        );
    }

    private static function set(string $code, mixed $definition): ItemSet
    {
        $definition = self::keys(self::map($definition, \sprintf('The set "%s"', $code)), self::SET_KEYS, \sprintf('the set "%s"', $code));
        $kind = SetKind::tryFrom(self::string($definition['kind'] ?? SetKind::Plain->value, \sprintf('The kind of "%s"', $code)))
            ?? throw InvalidCollection::because(\sprintf('The kind of "%s" is "plain", "base", "earned" or "paid".', $code));
        $rewards = $definition['rewards'] ?? [];

        if (!\is_array($rewards) || !array_is_list($rewards)) {
            throw InvalidCollection::because(\sprintf('The rewards of "%s" are a list.', $code));
        }

        return new ItemSet(
            $code,
            self::texts($definition['name'] ?? null, \sprintf('The name of "%s"', $code)),
            $kind,
            array_map(static fn (mixed $reward): Reward => self::reward($code, $reward), $rewards),
        );
    }

    private static function pack(string $code, mixed $definition): Pack
    {
        $definition = self::keys(self::map($definition, \sprintf('The pack "%s"', $code)), self::PACK_KEYS, \sprintf('the pack "%s"', $code));
        $size = $definition['size'] ?? 1;
        $weights = [];

        foreach (self::map($definition['weights'] ?? [], \sprintf('The weights of "%s"', $code)) as $rarity => $weight) {
            if (!\is_string($rarity) || !\is_int($weight)) {
                throw InvalidCollection::because(\sprintf('The weights of "%s" are whole numbers by rarity.', $code));
            }

            $weights[$rarity] = $weight;
        }

        if (!\is_int($size)) {
            throw InvalidCollection::because(\sprintf('The size of "%s" is a whole number.', $code));
        }

        return new Pack(
            $code,
            self::texts($definition['name'] ?? null, \sprintf('The name of "%s"', $code)),
            $size,
            self::strings($definition['sets'] ?? [], \sprintf('The sets of "%s"', $code)),
            $weights,
        );
    }

    private static function die(string $code, mixed $definition): CollectibleDie
    {
        $definition = self::keys(self::map($definition, \sprintf('The die of "%s"', $code)), self::DIE_KEYS, \sprintf('the die of "%s"', $code));
        $sides = $definition['sides'] ?? null;

        if (!\is_int($sides)) {
            throw InvalidCollection::because(\sprintf('The die of "%s" has a whole number of sides.', $code));
        }

        return new CollectibleDie(
            $sides,
            null === ($definition['color'] ?? null) ? null : self::string($definition['color'], \sprintf('The colour of the die of "%s"', $code)),
            null === ($definition['material'] ?? null) ? null : self::string($definition['material'], \sprintf('The material of the die of "%s"', $code)),
        );
    }

    private static function reward(string $set, mixed $definition): Reward
    {
        $definition = self::keys(self::map($definition, \sprintf('A reward of "%s"', $set)), self::REWARD_KEYS, \sprintf('a reward of "%s"', $set));
        $amount = $definition['amount'] ?? 1;

        if (!\is_int($amount)) {
            throw InvalidCollection::because(\sprintf('A reward of "%s" gives a whole amount.', $set));
        }

        return new Reward(
            self::string($definition['kind'] ?? null, \sprintf('The kind of a reward of "%s"', $set)),
            self::string($definition['code'] ?? null, \sprintf('What a reward of "%s" names', $set)),
            $amount,
        );
    }

    /**
     * @template T
     *
     * @param array<array-key, mixed>    $definition
     * @param callable(string, mixed): T $read
     *
     * @return list<T>
     */
    private static function each(array $definition, string $key, callable $read): array
    {
        $values = [];

        foreach (self::map($definition[$key] ?? [], \sprintf('The %s', $key)) as $code => $value) {
            $values[] = $read((string) $code, $value);
        }

        return $values;
    }

    /**
     * @return array<array-key, mixed>
     */
    private static function map(mixed $value, string $what): array
    {
        if (!\is_array($value)) {
            throw InvalidCollection::because(\sprintf('%s is a map of values.', $what));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $definition
     * @param list<string>            $allowed
     *
     * @return array<array-key, mixed>
     */
    private static function keys(array $definition, array $allowed, string $what): array
    {
        $unknown = array_diff(array_keys($definition), $allowed);

        if ([] !== $unknown) {
            throw InvalidCollection::because(\sprintf('Unknown key "%s" in %s; the keys are %s.', implode('", "', $unknown), $what, implode(', ', $allowed)));
        }

        return $definition;
    }

    private static function texts(mixed $value, string $what): Texts
    {
        if (!\is_string($value) && !\is_array($value)) {
            throw InvalidCollection::because(\sprintf('%s is a text, or texts by locale.', $what));
        }

        return Texts::of($value);
    }

    private static function string(mixed $value, string $what): string
    {
        if (!\is_string($value)) {
            throw InvalidCollection::because(\sprintf('%s is a text.', $what));
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private static function strings(mixed $value, string $what): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw InvalidCollection::because(\sprintf('%s are a list of codes.', $what));
        }

        return array_map(static fn (mixed $one): string => self::string($one, 'Each of '.lcfirst($what)), $value);
    }
}
