<?php

declare(strict_types=1);

namespace Tilttek\GeekDates;

use DateTimeImmutable;
use Phil\Almanac\Definition;
use Phil\Almanac\Rule\Rule;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Tilttek\GeekDates\Exception\InvalidEntry;

/**
 * One date of the catalog: its stable identifier, its names by locale, the rule of `phil/almanac` that finds it every
 * year, how many days it lasts, where the rule comes from, the country or community that keeps it, when the rule was
 * last checked against its source, and its tags.
 */
final readonly class Entry
{
    /** The most locales an entry names itself in. */
    private const int MAX_LOCALES = 32;

    /** The most tags an entry carries. */
    private const int MAX_TAGS = 16;

    /** The longest name, in characters. */
    private const int MAX_NAME = 200;

    /** A lowercase letter or digit, then up to 63 of them or "-": an identifier, a community or a tag. */
    private const string SLUG = '/^[a-z0-9][a-z0-9-]{0,63}$/D';

    /** The address the rule was checked against: an https address, in the canonical form of `phil/url`. */
    public string $source;

    private Definition $definition;

    /**
     * @param array<string, string> $names by locale, English and French included
     * @param list<string>          $tags
     */
    public function __construct(
        public string $id,
        public array $names,
        public Rule $rule,
        string $source,
        public string $community,
        public DateTimeImmutable $verified,
        public array $tags = [],
        public int $days = 1,
        public Status $status = Status::Verified,
    ) {
        if (1 !== preg_match(self::SLUG, $id)) {
            throw InvalidEntry::because($id, 'has no identifier: a lowercase letter or digit, then up to 63 of them or "-".');
        }

        $this->checkNames($id, $names);

        $this->source = $this->checkSource($id, $source);

        if (1 !== preg_match(self::SLUG, $community)) {
            throw InvalidEntry::because($id, 'has no community: a lowercase letter or digit, then up to 63 of them or "-".');
        }

        $this->checkTags($id, $tags);

        if ($days < 1 || $days > 366) {
            throw InvalidEntry::because($id, 'lasts 1 to 366 days.');
        }

        $this->definition = new Definition($id, $names['en'], $rule, days: $days, tags: $tags);
    }

    /**
     * Its name in a locale: `fr_CA` reads `fr_CA`, then `fr`, then English.
     */
    public function name(string $locale): string
    {
        return $this->names[$locale] ?? $this->names[strtolower(substr($locale, 0, strcspn($locale, '_-')))] ?? $this->names['en'];
    }

    /**
     * Whether it carries a tag.
     */
    public function hasTag(string $tag): bool
    {
        return \in_array($tag, $this->tags, true);
    }

    /**
     * The definition `phil/almanac` computes: its identifier as key, its English name as title, its rule, days and
     * tags.
     */
    public function definition(): Definition
    {
        return $this->definition;
    }

    /**
     * @param array<string, string> $names
     */
    private function checkNames(string $id, array $names): void
    {
        if (!isset($names['en'], $names['fr']) || \count($names) > self::MAX_LOCALES) {
            throw InvalidEntry::because($id, 'is named in English and French, in at most 32 locales.');
        }

        foreach ($names as $locale => $name) {
            if (1 !== preg_match('/^[a-z]{2,3}(?:_[A-Z]{2})?$/D', $locale)) {
                throw InvalidEntry::because($id, \sprintf('names itself in "%s", which is not a locale such as "fr" or "fr_CA".', mb_substr($locale, 0, 20)));
            }

            if ('' === trim($name) || mb_strlen($name) > self::MAX_NAME) {
                throw InvalidEntry::because($id, \sprintf('has a name in "%s" of 1 to 200 characters, not blank.', $locale));
            }
        }
    }

    /**
     * The source read by `phil/url`, as a browser reads it: an https address on a host name, without credentials or
     * surrounding spaces, 2048 bytes at most in its canonical form, which is the one kept.
     */
    private function checkSource(string $id, string $source): string
    {
        try {
            $url = trim($source) === $source ? Url::fromString($source) : null;
        } catch (UrlException) {
            $url = null;
        }

        if (!$url instanceof Url || 'https' !== $url->scheme) {
            throw InvalidEntry::because($id, 'has no source: an https address on a host name, of at most 2048 bytes, without spaces.');
        }

        return $url->toString();
    }

    /**
     * @param list<string> $tags
     */
    private function checkTags(string $id, array $tags): void
    {
        if (\count($tags) > self::MAX_TAGS || \count(array_unique($tags)) !== \count($tags)) {
            throw InvalidEntry::because($id, 'carries at most 16 tags, each once.');
        }

        foreach ($tags as $tag) {
            if (1 !== preg_match(self::SLUG, $tag)) {
                throw InvalidEntry::because($id, \sprintf('carries the tag "%s": a lowercase letter or digit, then up to 63 of them or "-".', mb_substr($tag, 0, 64)));
            }
        }
    }
}
