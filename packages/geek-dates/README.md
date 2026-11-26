# tilttek/geek-dates

**Geek culture days on the right date every year — Towel Day, Pi Day, Ada Lovelace Day —, each with its source.**

tilttek/geek-dates is a catalog of the days geek culture keeps: science and mathematics days, computing days, the
days of readers of Tolkien or Douglas Adams. Each entry is a rule of `phil/almanac` — `05-25`, `last friday of july`,
`2nd tuesday of october`, `day 256` — never a list of dates by year, with its names in English and French, the
address of the source its rule was checked against, the country or community that keeps it, the day it was last
checked and its tags. The catalog computes the dates of a year, of a period or of a day — the object of the day —,
filtered by tag, by community or to the verified entries, and every date it gives carries its entry and source.

An application adds its own entries — a local club's day, a convention whose date it knows — in the same form, from
arrays or in code, without touching the shipped data. `DataCheck` lists every problem of a set of rows: a rule that
does not read, a missing source or date of verification, an identifier found twice.

It is for an application that shows dates of geek culture: an object of the day, an advent calendar, seasonal
events, a draw on a given day. It is a plain PHP library that depends on `phil/almanac`, `phil/url` and
`ext-mbstring`. The data lists no commercial "national day", no date a brand declares about itself, and no convention
date.

## Status

| Part | State |
|---|---|
| `Entry`, `Status`: entries with names, rule, source, community, date of verification, tags and days | Implemented and tested |
| `EntryReader`: entries from arrays, the shipped data's form and an application's | Implemented and tested |
| `Catalog`, `Filter`, `Occurrence`: the dates of a year, a period or a day, filtered, with their source | Implemented and tested |
| `ShippedData`, `data/dates.php`, `data/VERSION`: 18 entries, each checked against its source | Implemented and tested |
| `DataCheck`: every problem of a set of rows | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Reference](doc/reference.md) | Every public type, with its role, and the catalog's questions. |
| [Data](doc/data.md) | The shipped entries and their sources, what is left out, adding an entry. |
| [Exceptions](doc/exceptions.md) | What each exception means, and the problems `DataCheck` lists. |
| [Extension points](doc/extension-points.md) | What an application may add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests and benchmarks. |

## Quick start

```php
use Tilttek\GeekDates\Catalog;
use Tilttek\GeekDates\EntryReader;
use Tilttek\GeekDates\Filter;

$catalog = Catalog::shipped(new DateTimeZone('America/Toronto'), [
    new EntryReader()->read([
        'id' => 'club-anniversary',
        'names' => ['en' => 'Club anniversary', 'fr' => 'Anniversaire du club'],
        'rule' => '2nd saturday of march',
        'source' => 'https://club.example/about',
        'community' => 'our-club',
        'verified' => '2026-11-21',
    ]),
]);

foreach ($catalog->on(new DateTimeImmutable('2026-05-25')) as $date) {
    echo $date->entry->name('fr_CA'), ' — ', $date->source(), "\n";
}

$science = $catalog->year(2027, new Filter(tags: ['science']));
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/almanac` 0.1; `phil/url` 0.1, with `ext-intl` and `ext-uri`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE). The data
holds facts — a day and its rule — checked against the sources [Data](doc/data.md#sources) lists; its names are the
package's own.
