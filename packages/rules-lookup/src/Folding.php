<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup;

use Normalizer;

/**
 * The folding that makes a search ignore case and accents — `Épée` and `epee` match —, by Unicode normalisation: the
 * `Normalizer` of intl, or of `symfony/polyfill-intl-normalizer` without the extension; and the words of a folded
 * text. It keeps nothing between two calls.
 *
 * @internal
 */
final class Folding
{
    private const string KEY = '/^[a-z0-9](?:[a-z0-9_-]|\.(?=[a-z0-9])){0,95}$/D';

    private const string LOCALE = '/^([a-z]{2,3})(?:_([A-Z]{2}|[0-9]{3}))?$/D';

    /**
     * A text without case nor accents: decomposed by NFKD, its nonspacing marks (`\p{Mn}`) removed, lowercased,
     * recomposed by NFC. A text that is not valid UTF-8 folds to the empty string.
     */
    public static function fold(string $text): string
    {
        // Plain ASCII has neither accents nor compatibility forms: lowercasing it is the whole folding, and much faster.
        if (1 !== preg_match('/[^\x00-\x7F]/', $text)) {
            return strtolower($text);
        }

        $decomposed = Normalizer::normalize($text, Normalizer::FORM_KD);

        if (false === $decomposed) {
            return '';
        }

        $bare = (string) preg_replace('/\p{Mn}+/u', '', $decomposed);

        return (string) Normalizer::normalize(mb_strtolower($bare, 'UTF-8'), Normalizer::FORM_C);
    }

    /**
     * The words of a text, folded, each once, in the order they first appear: runs of letters and digits.
     *
     * @return list<string>
     */
    public static function words(string $text): array
    {
        $words = preg_split('/[^\p{L}\p{N}]+/u', self::fold($text), -1, \PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(false === $words ? [] : $words));
    }

    /**
     * Whether a key is lowercase ASCII letters, digits, `_`, `-` and single dots between them, 96 characters at most —
     * the keys of `tilttek/rpg-systems`.
     */
    public static function isKey(string $key): bool
    {
        return 1 === preg_match(self::KEY, $key);
    }

    /**
     * A locale written `fr`, `fr_CA`, `fr-ca` or `es-419`, given back as `fr`, `fr_CA` or `es_419`; null when it is
     * not one.
     */
    public static function locale(string $locale): ?string
    {
        $parts = explode('_', str_replace('-', '_', $locale), 2);
        $normalised = strtolower($parts[0]).(isset($parts[1]) ? '_'.strtoupper($parts[1]) : '');

        return 1 === preg_match(self::LOCALE, $normalised) ? $normalised : null;
    }
}
