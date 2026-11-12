<?php

declare(strict_types=1);

namespace Tilttek\Weather;

use Tilttek\Weather\Exception\InvalidClimate;

/**
 * The checks the declared climates share: identifiers, labels and bounded whole numbers.
 *
 * @internal
 */
final class Rules
{
    /**
     * The longest label, in characters.
     */
    public const int LABEL_LENGTH = 100;

    private function __construct()
    {
    }

    /**
     * An identifier: a lowercase letter, then up to 31 lowercase letters, digits, `_` or `-`; it names a condition, a
     * season or a zone in a state and in the names of the generator's lists.
     *
     * @throws InvalidClimate
     */
    public static function identifier(string $place, string $id): string
    {
        if (1 !== preg_match('/^[a-z][a-z0-9_-]{0,31}$/D', $id)) {
            throw InvalidClimate::at($place, \sprintf('"%s" is not an identifier: a lowercase letter, then up to 31 lowercase letters, digits, "_" or "-"', $id));
        }

        return $id;
    }

    /**
     * Labels by language: at least one, each a text of 1 to 100 characters under a language code.
     *
     * @param array<array-key, mixed> $labels
     *
     * @return array<string, string>
     *
     * @throws InvalidClimate
     */
    public static function labels(string $place, array $labels): array
    {
        if ([] === $labels) {
            throw InvalidClimate::at($place, 'at least one label is given');
        }

        $checked = [];

        foreach ($labels as $locale => $label) {
            if (!\is_string($locale) || 1 !== preg_match('/^[a-z]{2,3}(_[A-Z]{2})?$/D', $locale)) {
                throw InvalidClimate::at($place, \sprintf('"%s" is not a language code such as "en" or "fr_CA"', $locale));
            }

            if (!\is_string($label) || '' === trim($label) || mb_strlen($label) > self::LABEL_LENGTH) {
                throw InvalidClimate::at($place.'.'.$locale, \sprintf('a label is a text of 1 to %d characters', self::LABEL_LENGTH));
            }

            $checked[$locale] = $label;
        }

        return $checked;
    }

    /**
     * @throws InvalidClimate
     */
    public static function between(string $place, int $value, int $lowest, int $highest): int
    {
        if ($value < $lowest || $value > $highest) {
            throw InvalidClimate::at($place, \sprintf('%d is not a whole number from %d to %d', $value, $lowest, $highest));
        }

        return $value;
    }
}
