<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\RpgSystems\Exception\InvalidRecord;

/**
 * The checks every record shares: keys, locales and texts.
 *
 * @internal
 */
final class Text
{
    /** The longest text a record holds: a name, an attribution, a description. */
    public const int MAX_LENGTH = 4000;

    private const string KEY = '/^[a-z0-9](?:[a-z0-9_-]|\.(?=[a-z0-9])){0,95}$/D';

    private const string LOCALE = '/^([a-z]{2,3})(?:_([A-Z]{2}|[0-9]{3}))?$/D';

    /**
     * A key: lowercase ASCII letters, digits, `_`, `-` and single dots between them, 96 characters at most.
     *
     * @throws InvalidRecord
     */
    public static function key(string $key, string $what): string
    {
        if (1 !== preg_match(self::KEY, $key)) {
            throw InvalidRecord::because(\sprintf('The key of %s "%s" must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them.', $what, $key));
        }

        return $key;
    }

    /**
     * A locale written `fr`, `fr_CA`, `fr-ca` or `es-419`, given back as `fr`, `fr_CA` or `es_419`.
     *
     * @throws InvalidRecord
     */
    public static function locale(string $locale): string
    {
        $parts = explode('_', str_replace('-', '_', $locale), 2);
        $normalised = strtolower($parts[0]).(isset($parts[1]) ? '_'.strtoupper($parts[1]) : '');

        if (1 !== preg_match(self::LOCALE, $normalised)) {
            throw InvalidRecord::because(\sprintf('"%s" is not a locale such as "en", "fr_CA" or "es-419".', $locale));
        }

        return $normalised;
    }

    /**
     * A text of at most `MAX_LENGTH` characters, UTF-8, without control characters other than line breaks and tabs.
     *
     * @throws InvalidRecord
     */
    public static function check(string $text, string $what, bool $required = true): string
    {
        if ($required && '' === trim($text)) {
            throw InvalidRecord::because(\sprintf('%s is required.', $what));
        }

        if (!mb_check_encoding($text, 'UTF-8') || 1 === preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $text)) {
            throw InvalidRecord::because(\sprintf('%s is not UTF-8 or holds control characters.', $what));
        }

        if (mb_strlen($text) > self::MAX_LENGTH) {
            throw InvalidRecord::because(\sprintf('%s is longer than %d characters.', $what, self::MAX_LENGTH));
        }

        return $text;
    }
}
