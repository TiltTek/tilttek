# tilttek/rpg-calendar-events

**Roleplaying community events on the right date every year, and the day of a month of daily prompts, each with its
source.**

tilttek/rpg-calendar-events is a catalog of the yearly events of the roleplaying community: GM's Day, the month of
daily prompts of RPGaDAY, National Game Design Month. Each entry is a rule of `phil/almanac` — `03-04`, `08-01` for
31 days, `3rd saturday of june` — never a list of dates by year, with its names in English and French, the address of
the source its rule was checked against, the community that keeps it, the day it was last checked and its tags. The
catalog computes the dates of a year, of a period or of a day, filtered by tag, by community or to the verified
entries, and every date it gives carries its entry and source.

A themed period of daily prompts is a range with an index: on August 12, `promptDays()` gives the twelfth day of 31,
and the application shows its own twelfth prompt — the package ships no list of prompts. An application adds its own
events — the conventions it knows, a local game day — in the same form, without touching the shipped data.
`DataCheck` lists every problem of a set of rows.

It is for an application that turns the roleplaying calendar into quests of the day, promotions or reminders. It is a
plain PHP library that depends on `phil/almanac`, `phil/url` and `ext-mbstring`. The data lists no convention, no
event whose organiser sets its date each year, and no date a brand declares about itself.

## Status

| Part | State |
|---|---|
| `Entry`, `Status`: events with names, rule, source, community, date of verification, tags, days and prompts | Implemented and tested |
| `EntryReader`: events from arrays, the shipped data's form and an application's | Implemented and tested |
| `Catalog`, `Filter`, `Occurrence`: the dates of a year, a period or a day, filtered, with their source | Implemented and tested |
| `PromptDay`: the day of a themed period of daily prompts, with its index and length | Implemented and tested |
| `ShippedData`, `data/events.php`, `data/VERSION`: 3 events, each checked against its source | Implemented and tested |
| `DataCheck`: every problem of a set of rows | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Reference](doc/reference.md) | Every public type, with its role, the catalog's questions and the days of prompts. |
| [Data](doc/data.md) | The shipped events and their sources, what is left out, adding an event. |
| [Exceptions](doc/exceptions.md) | What each exception means, and the problems `DataCheck` lists. |
| [Extension points](doc/extension-points.md) | What an application may add, and when it is stable. |
| [Testing](doc/testing.md) | The package's tests and benchmarks. |

## Quick start

```php
use Tilttek\RpgCalendarEvents\Catalog;
use Tilttek\RpgCalendarEvents\EntryReader;

$catalog = Catalog::shipped(new DateTimeZone('America/Toronto'), [
    new EntryReader()->read([
        'id' => 'local-con',
        'names' => ['en' => 'Our convention', 'fr' => 'Notre convention'],
        'rule' => '3rd saturday of june',
        'days' => 2,
        'source' => 'https://convention.example/dates',
        'community' => 'montreal',
        'verified' => '2026-11-21',
    ]),
]);

foreach ($catalog->promptDays(new DateTimeImmutable('2027-08-12')) as $day) {
    echo $day->occurrence->entry->name('fr'), ': day ', $day->index, ' of ', $day->length(), "\n";
}

$events = $catalog->year(2027);
```

## Requirements

PHP 8.5 or later with `ext-mbstring`; `phil/almanac` 0.1; `phil/url` 0.1, with `ext-intl` and `ext-uri`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE). The data
holds facts — an event and its rule — checked against the sources [Data](doc/data.md#sources) lists; its names are
the package's own.
