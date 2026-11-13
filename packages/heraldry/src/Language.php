<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * The languages a blazon is written in.
 */
enum Language: string
{
    case English = 'en';
    case French = 'fr';

    /**
     * The language of a locale, `fr`, `fr_CA` or `fr-CA`; English for any other.
     */
    public static function ofLocale(string $locale): self
    {
        return self::tryFrom(strtolower(substr($locale, 0, 2))) ?? self::English;
    }
}
