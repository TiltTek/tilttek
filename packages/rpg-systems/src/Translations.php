<?php

declare(strict_types=1);

namespace Tilttek\RpgSystems;

use Tilttek\RpgSystems\Exception\InvalidRecord;

/**
 * A text in several languages — the name of an attribute, the description of a term —, by locale.
 */
final readonly class Translations
{
    /** The most locales one text holds. */
    public const int MAX_LOCALES = 64;

    /** @var array<string, string> the text by normalised locale */
    public array $texts;

    /**
     * @param array<string, string> $texts the text by locale, `['en' => 'Strength', 'fr' => 'Force']`
     * @param string                $what  what the text is, for the messages of the refusals
     *
     * @throws InvalidRecord
     */
    public function __construct(array $texts = [], string $what = 'A text')
    {
        if (\count($texts) > self::MAX_LOCALES) {
            throw InvalidRecord::because(\sprintf('%s has more than %d locales.', $what, self::MAX_LOCALES));
        }

        $normalised = [];

        foreach ($texts as $locale => $text) {
            $normalised[Text::locale($locale)] = Text::check($text, \sprintf('%s in "%s"', $what, $locale));
        }

        $this->texts = $normalised;
    }

    /**
     * @param array<string, string> $texts the text by locale
     *
     * @throws InvalidRecord
     */
    public static function of(array $texts, string $what = 'A text'): self
    {
        return new self($texts, $what);
    }

    /**
     * The text in a locale: the locale itself, `fr_CA`, then its language, `fr`, then the fallback locale, then the
     * first text held; null when it holds none.
     */
    public function in(string $locale, string $fallback = 'en'): ?string
    {
        $locale = Text::locale($locale);

        return $this->texts[$locale]
            ?? $this->texts[explode('_', $locale)[0]]
            ?? $this->texts[Text::locale($fallback)]
            ?? array_first($this->texts);
    }

    public function has(string $locale): bool
    {
        return isset($this->texts[Text::locale($locale)]);
    }

    /**
     * These texts with one more locale, or one replaced.
     *
     * @throws InvalidRecord
     */
    public function with(string $locale, string $text): self
    {
        return self::of([...$this->texts, Text::locale($locale) => $text]);
    }

    /**
     * @return list<string>
     */
    public function locales(): array
    {
        return array_keys($this->texts);
    }

    public function isEmpty(): bool
    {
        return [] === $this->texts;
    }
}
