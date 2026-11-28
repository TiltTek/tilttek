<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup;

use Tilttek\RulesLookup\Exception\InvalidQuery;

/**
 * A search in the rules: the words looked for, the reader's locale, the system, version and category it is narrowed
 * to, and the page of results asked for. The words are folded — without case nor accents — when the query is made.
 */
final readonly class RuleQuery
{
    /** The longest text searched for, in characters. */
    public const int MAX_LENGTH = 200;

    /** The most words a search holds. */
    public const int MAX_WORDS = 16;

    /** The most results one page holds. */
    public const int MAX_LIMIT = 100;

    /** The furthest a page starts. */
    public const int MAX_OFFSET = 10_000;

    /** @var list<string> the words searched for, folded, each once */
    public array $words;

    /** The locale of the reader, normalised: `fr_CA`. */
    public string $locale;

    /**
     * @param string  $text     the words looked for; empty to list a category
     * @param string  $locale   the reader's locale: the texts searched and shown are those of this locale, of its
     *                          language, of the fallback locale, then the first a rule holds
     * @param ?string $system   only the rules of this system
     * @param ?string $version  only the rules of this version of the system
     * @param ?string $category only the rules of this category
     *
     * @throws InvalidQuery
     */
    public function __construct(
        public string $text,
        string $locale = 'en',
        public ?string $system = null,
        public ?string $version = null,
        public ?string $category = null,
        public int $limit = 20,
        public int $offset = 0,
    ) {
        if (!mb_check_encoding($text, 'UTF-8') || mb_strlen($text) > self::MAX_LENGTH) {
            throw InvalidQuery::because(\sprintf('The text searched for must be UTF-8 and at most %d characters long.', self::MAX_LENGTH));
        }

        $this->words = Folding::words($text);

        if (\count($this->words) > self::MAX_WORDS) {
            throw InvalidQuery::because(\sprintf('A search holds at most %d words.', self::MAX_WORDS));
        }

        if ([] === $this->words && null === $category) {
            throw InvalidQuery::because('A search needs words or a category.');
        }

        $this->locale = Folding::locale($locale) ?? throw InvalidQuery::because(\sprintf('"%s" is not a locale such as "en", "fr_CA" or "es-419".', $locale));

        foreach (['system' => $system, 'category' => $category] as $what => $key) {
            if (null !== $key && !Folding::isKey($key)) {
                throw InvalidQuery::because(\sprintf('The %s "%s" is not a key.', $what, $key));
            }
        }

        if ($limit < 1 || $limit > self::MAX_LIMIT || $offset < 0 || $offset > self::MAX_OFFSET) {
            throw InvalidQuery::because(\sprintf('A page holds 1 to %d results and starts at 0 to %d.', self::MAX_LIMIT, self::MAX_OFFSET));
        }
    }

    /**
     * The words joined by single spaces, the folded phrase a title is compared with.
     */
    public function phrase(): string
    {
        return implode(' ', $this->words);
    }
}
