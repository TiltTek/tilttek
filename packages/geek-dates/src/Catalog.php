<?php

declare(strict_types=1);

namespace Tilttek\GeekDates;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Phil\Almanac\Almanac;
use Phil\Almanac\Definition;
use Tilttek\GeekDates\Exception\InvalidEntry;
use Tilttek\GeekDates\Exception\InvalidQuery;

/**
 * The entries, shipped or an application's own, computed by `phil/almanac` in one time zone: the dates of a year, of
 * a period or of a day, each with its entry and source. It keeps no state but its entries and never changes.
 */
final readonly class Catalog
{
    /** The years a question reads. */
    private const int FIRST_YEAR = 1000;

    private const int LAST_YEAR = 3000;

    /** @var array<string, Entry> */
    private array $entries;

    private Almanac $almanac;

    /**
     * @param iterable<Entry> $entries
     */
    public function __construct(
        iterable $entries,
        private DateTimeZone $zone,
    ) {
        $byId = [];

        foreach ($entries as $entry) {
            if (isset($byId[$entry->id])) {
                throw InvalidEntry::because($entry->id, 'is in the catalog twice.');
            }

            $byId[$entry->id] = $entry;
        }

        $this->entries = $byId;
        $this->almanac = new Almanac(array_map(static fn (Entry $entry): Definition => $entry->definition(), $byId), $zone);
    }

    /**
     * The catalog of the shipped data, with an application's own entries after them.
     *
     * @param iterable<Entry> $own
     */
    public static function shipped(DateTimeZone $zone, iterable $own = []): self
    {
        return new self([...ShippedData::entries(), ...$own], $zone);
    }

    /**
     * A catalog with more entries: an identifier already held is refused.
     */
    public function with(Entry ...$entries): self
    {
        return new self([...$this->entries, ...$entries], $this->zone);
    }

    /**
     * @return array<string, Entry> by identifier, in the order given
     */
    public function entries(): array
    {
        return $this->entries;
    }

    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }

    public function entry(string $id): Entry
    {
        return $this->entries[$id] ?? throw InvalidQuery::because(\sprintf('The catalog holds no entry "%s".', mb_substr($id, 0, 100)));
    }

    /**
     * The dates that touch a year, by start, then English name.
     *
     * @return list<Occurrence>
     */
    public function year(int $year, ?Filter $filter = null): array
    {
        if ($year < self::FIRST_YEAR || $year > self::LAST_YEAR) {
            throw InvalidQuery::because(\sprintf('The catalog reads the years 1000 to 3000, not %d.', $year));
        }

        $from = new DateTimeImmutable(\sprintf('%04d-01-01', $year), $this->zone);

        return $this->between($from, $from->modify('+1 year'), $filter);
    }

    /**
     * The dates that touch a period, its start included and its end not, by start, then English name.
     *
     * @return list<Occurrence>
     */
    public function between(DateTimeInterface $from, DateTimeInterface $until, ?Filter $filter = null): array
    {
        if ($from < new DateTimeImmutable('1000-01-01', $this->zone) || $until > new DateTimeImmutable('3001-01-01', $this->zone)) {
            throw InvalidQuery::because('The catalog reads the years 1000 to 3000: a period starts on 1000-01-01 or later and ends on 3001-01-01 or earlier.');
        }

        $found = [];

        foreach ($this->almanac->between($from, $until) as $date) {
            $entry = $this->entries[$date->definition->key];

            if (!$filter instanceof Filter || $filter->accepts($entry)) {
                $found[] = new Occurrence($entry, $date->span);
            }
        }

        return $found;
    }

    /**
     * The dates that cover a day, read in the catalog's time zone: the object of the day.
     *
     * @return list<Occurrence>
     */
    public function on(DateTimeInterface $day, ?Filter $filter = null): array
    {
        $midnight = DateTimeImmutable::createFromInterface($day)->setTimezone($this->zone)->setTime(0, 0);

        return $this->between($midnight, $midnight->modify('+1 day'), $filter);
    }

    public function zone(): DateTimeZone
    {
        return $this->zone;
    }
}
