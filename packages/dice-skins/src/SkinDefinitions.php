<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

use JsonException;
use Tilttek\DiceSkins\Exception\InvalidSkin;

/**
 * Reads the skins a site offers from plain values or from JSON — a file of the application, a form of its back
 * office —, as `Skin::toArray()` writes them:
 *
 *     [{"key": "ember", "label": "Ember", "face": "#9b1b30", "ink": "#ffffff", "material": "pearl",
 *       "texture": "pearl/soft", "types": {"20": {"face": "#1b1b1f", "ink": "#f5f5f5"}}}]
 *
 * The look of a type of dice takes from the look of the skin what it does not say. Whatever the input, the reader
 * gives skins or refuses with `InvalidSkin`: a field it does not know, a value of the wrong type, two skins with the
 * same key, more than 256 skins, a JSON text over 256 KiB or deeper than 8 levels.
 */
final class SkinDefinitions
{
    public const int MAX_SKINS = 256;

    public const int MAX_JSON_BYTES = 262_144;

    public const int MAX_DEPTH = 8;

    private const array SKIN_FIELDS = ['key', 'label', 'face', 'ink', 'material', 'texture', 'types'];

    private const array LOOK_FIELDS = ['face', 'ink', 'material', 'texture'];

    private function __construct()
    {
    }

    /**
     * @return list<Skin>
     *
     * @throws InvalidSkin when the text is not a list of skins as described above
     */
    public static function fromJson(string $json): array
    {
        if (\strlen($json) > self::MAX_JSON_BYTES) {
            throw InvalidSkin::because(\sprintf('A text of skin definitions holds %d bytes at most.', self::MAX_JSON_BYTES));
        }

        try {
            $definitions = json_decode($json, true, self::MAX_DEPTH, \JSON_THROW_ON_ERROR);
        } catch (JsonException $jsonException) {
            throw InvalidSkin::because(\sprintf('The skin definitions are not JSON of %d levels at most: %s.', self::MAX_DEPTH, $jsonException->getMessage()));
        }

        if (!\is_array($definitions)) {
            throw InvalidSkin::because('The skin definitions are a list of skins.');
        }

        return self::fromArray($definitions);
    }

    /**
     * @param array<array-key, mixed> $definitions
     *
     * @return list<Skin>
     *
     * @throws InvalidSkin when the values are not a list of skins as described above
     */
    public static function fromArray(array $definitions): array
    {
        if (!array_is_list($definitions)) {
            throw InvalidSkin::because('The skin definitions are a list of skins.');
        }

        if (\count($definitions) > self::MAX_SKINS) {
            throw InvalidSkin::because(\sprintf('A site offers %d skins at most; the definitions hold %d.', self::MAX_SKINS, \count($definitions)));
        }

        $skins = [];

        foreach ($definitions as $definition) {
            $skin = self::skin($definition);

            if (isset($skins[$skin->key])) {
                throw InvalidSkin::because(\sprintf('Two skins have the key "%s".', $skin->key));
            }

            $skins[$skin->key] = $skin;
        }

        return array_values($skins);
    }

    private static function skin(mixed $definition): Skin
    {
        if (!\is_array($definition)) {
            throw InvalidSkin::because('A skin is an object of fields.');
        }

        self::fields($definition, self::SKIN_FIELDS, 'a skin');
        $key = self::text($definition, 'key', 'a skin');
        $where = \sprintf('the skin "%s"', mb_substr($key, 0, 32));
        $look = new SkinLook(
            Color::fromHex(self::text($definition, 'face', $where)),
            Color::fromHex(self::text($definition, 'ink', $where)),
            self::material($definition, $where) ?? Material::Solid,
            self::texture($definition, $where),
        );

        return new Skin($key, self::text($definition, 'label', $where), $look, self::types($definition['types'] ?? [], $look, $where));
    }

    /**
     * @return array<int, SkinLook>
     */
    private static function types(mixed $types, SkinLook $look, string $where): array
    {
        if (!\is_array($types)) {
            throw InvalidSkin::because(\sprintf('The types of %s are an object of looks by number of sides.', $where));
        }

        $looks = [];

        foreach ($types as $sides => $type) {
            // JSON keys that are integers arrive as integers: any other key is not a number of sides.
            if (!\is_int($sides)) {
                throw InvalidSkin::because(\sprintf('A type of dice of %s is named by its number of sides; "%s" is not one.', $where, mb_substr($sides, 0, 16)));
            }

            if (!\is_array($type)) {
                throw InvalidSkin::because(\sprintf('The look of the d%s of %s is an object of fields.', $sides, $where));
            }

            $what = \sprintf('the d%s of %s', $sides, $where);
            self::fields($type, self::LOOK_FIELDS, $what);
            $looks[Skin::sides($sides)] = new SkinLook(
                isset($type['face']) ? Color::fromHex(self::text($type, 'face', $what)) : $look->face,
                isset($type['ink']) ? Color::fromHex(self::text($type, 'ink', $what)) : $look->ink,
                self::material($type, $what) ?? $look->material,
                self::texture($type, $what) ?? $look->texture,
            );
        }

        return $looks;
    }

    /**
     * @param array<array-key, mixed> $values
     * @param list<string>            $known
     */
    private static function fields(array $values, array $known, string $where): void
    {
        foreach (array_keys($values) as $field) {
            if (!\in_array($field, $known, true)) {
                throw InvalidSkin::because(\sprintf('The field "%s" of %s is not one of %s.', mb_substr((string) $field, 0, 32), $where, implode(', ', $known)));
            }
        }
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function text(array $values, string $field, string $where): string
    {
        $value = $values[$field] ?? null;

        if (!\is_string($value)) {
            throw InvalidSkin::because(\sprintf('The field "%s" of %s is a text.', $field, $where));
        }

        return $value;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function material(array $values, string $where): ?Material
    {
        if (!isset($values['material'])) {
            return null;
        }

        $material = Material::tryFrom(self::text($values, 'material', $where));

        if (!$material instanceof Material) {
            throw InvalidSkin::because(\sprintf('The material of %s is one of %s.', $where, implode(', ', array_map(static fn (Material $case): string => $case->value, Material::cases()))));
        }

        return $material;
    }

    /**
     * @param array<array-key, mixed> $values
     */
    private static function texture(array $values, string $where): ?TextureReference
    {
        return isset($values['texture']) ? new TextureReference(self::text($values, 'texture', $where)) : null;
    }
}
