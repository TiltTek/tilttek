<?php

declare(strict_types=1);

namespace Tilttek\GeekDates;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Almanac\Exception\InvalidRule;
use Phil\Almanac\Rule\RuleParser;
use Tilttek\GeekDates\Exception\InvalidEntry;

/**
 * Reads entries from arrays, the form of the shipped data file and of an application's own entries.
 *
 * A row holds `id`, `names` (by locale, English and French included), `rule` (the text `phil/almanac` reads),
 * `source` (an https address), `community`, `verified` (`YYYY-MM-DD`), and optionally `tags`, `days` and `status`
 * (`verified` or `to-verify`). Any other key is refused.
 */
final class EntryReader
{
    private const array KEYS = ['id', 'names', 'rule', 'source', 'community', 'verified', 'tags', 'days', 'status'];

    /**
     * @param array<mixed> $row
     */
    public function read(array $row): Entry
    {
        $id = \is_string($row['id'] ?? null) ? $row['id'] : '?';
        $unknown = array_diff(array_keys($row), self::KEYS);

        if ([] !== $unknown) {
            throw InvalidEntry::because($id, \sprintf('has the unknown field "%s".', mb_substr((string) reset($unknown), 0, 64)));
        }

        try {
            $rule = RuleParser::parse($this->string($row, 'rule', $id));
        } catch (InvalidRule $invalidRule) {
            throw InvalidEntry::because($id, 'has a rule that cannot be read: '.$invalidRule->getMessage());
        }

        return new Entry(
            $this->string($row, 'id', $id),
            $this->names($row['names'] ?? null, $id),
            $rule,
            $this->string($row, 'source', $id),
            $this->string($row, 'community', $id),
            $this->date($this->string($row, 'verified', $id), $id),
            $this->tags($row['tags'] ?? [], $id),
            $this->days($row['days'] ?? 1, $id),
            Status::tryFrom($this->string($row + ['status' => Status::Verified->value], 'status', $id)) ?? throw InvalidEntry::because($id, 'has a status that is neither "verified" nor "to-verify".'),
        );
    }

    /**
     * @param iterable<array<mixed>> $rows
     *
     * @return list<Entry>
     */
    public function readAll(iterable $rows): array
    {
        $entries = [];

        foreach ($rows as $row) {
            $entries[] = $this->read($row);
        }

        return $entries;
    }

    /**
     * @param array<mixed> $row
     */
    private function string(array $row, string $key, string $id): string
    {
        return \is_string($row[$key] ?? null) ? $row[$key] : throw InvalidEntry::because($id, \sprintf('has no "%s" text.', $key));
    }

    /**
     * @return array<string, string>
     */
    private function names(mixed $names, string $id): array
    {
        if (!\is_array($names)) {
            throw InvalidEntry::because($id, 'has no "names" by locale.');
        }

        $byLocale = [];

        foreach ($names as $locale => $name) {
            if (!\is_string($locale) || !\is_string($name)) {
                throw InvalidEntry::because($id, 'has a name that is not a text under its locale.');
            }

            $byLocale[$locale] = $name;
        }

        return $byLocale;
    }

    /**
     * @return list<string>
     */
    private function tags(mixed $tags, string $id): array
    {
        if (!\is_array($tags) || !array_is_list($tags) || [] !== array_filter($tags, static fn (mixed $tag): bool => !\is_string($tag))) {
            throw InvalidEntry::because($id, 'has "tags" that are not a list of texts.');
        }

        /** @var list<string> $tags */
        return $tags;
    }

    private function days(mixed $days, string $id): int
    {
        return \is_int($days) ? $days : throw InvalidEntry::because($id, 'has "days" that is not a whole number.');
    }

    private function date(string $text, string $id): DateTimeImmutable
    {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $text, new DateTimeZone('UTC'));

        if (false === $date || $date->format('Y-m-d') !== $text) {
            throw InvalidEntry::because($id, \sprintf('was verified on "%s", which is not a date such as 2026-11-21.', mb_substr($text, 0, 20)));
        }

        return $date;
    }
}
