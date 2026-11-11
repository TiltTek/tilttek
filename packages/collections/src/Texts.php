<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Tilttek\Collections\Exception\InvalidCollection;

/**
 * A text in several languages — the name or the description of an item, a set or a pack —, by locale: `en`, `fr`,
 * `fr_CA`. A single text is written under `en`.
 */
final readonly class Texts
{
    public const int MAX_LENGTH = 2000;

    /**
     * @param non-empty-array<string, string> $texts
     */
    private function __construct(
        public array $texts,
    ) {
    }

    /**
     * @param string|array<array-key, mixed> $texts
     */
    public static function of(string|array $texts): self
    {
        if (\is_string($texts)) {
            $texts = ['en' => $texts];
        }

        if ([] === $texts) {
            throw InvalidCollection::because('A text is written in at least one language.');
        }

        $checked = [];

        foreach ($texts as $locale => $text) {
            if (!\is_string($locale) || 1 !== preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/D', $locale)) {
                throw InvalidCollection::because(\sprintf('"%s" is not a locale, such as "en", "fr" or "fr_CA".', $locale));
            }

            if (!\is_string($text) || '' === trim($text) || mb_strlen($text) > self::MAX_LENGTH) {
                throw InvalidCollection::because(\sprintf('The text in "%s" is 1 to %d characters.', $locale, self::MAX_LENGTH));
            }

            $checked[$locale] = $text;
        }

        return new self($checked);
    }

    /**
     * The text in a locale: that locale, then its language, then English, then the first one written.
     */
    public function in(string $locale): string
    {
        return $this->texts[$locale] ?? $this->texts[explode('_', $locale)[0]] ?? $this->texts['en'] ?? array_first($this->texts);
    }
}
