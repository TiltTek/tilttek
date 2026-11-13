# Definitions

`SeasonReader` reads seasons from plain arrays — a PHP configuration, a JSON or YAML file — and writes them back:

```php
[
    'id' => 'winter-festival',
    'timezone' => 'America/Toronto',
    'yearly' => ['from' => '2026-12-15', 'days' => 23],     // or 'once' => ['from' => '2027-03-20', 'through' => '2027-04-03']
    'quests' => ['quest:winter-hunt'],
    'items' => ['item:snow-hat'],
    'decorations' => ['decoration:snowflakes'],
    'events' => [
        ['id' => 'gift-week', 'from_day' => 8, 'days' => 7, 'announce_days' => 7,
         'items' => ['item:wrapped-die'], 'multiplier' => ['ledger' => 'points', 'percent' => 200]],
    ],
]
```

| Key | Holds |
|---|---|
| `id`, `timezone` | The season's identifier and timezone, required. |
| `once` | `from` and `through`, both days included. |
| `yearly` | `from`, `days`, and `rule`, `FREQ=YEARLY` by default. |
| `quests`, `items`, `decorations` | References, `kind:identifier`. |
| `events` | A list of events: `id`, `from_day` (1 by default), `days` (to the season's end when left out), `announce_days` (0 by default), the three lists of contents, and `multiplier` with `ledger` and `percent`. |

A season comes `once` or `yearly`, one of the two. A key the reader does not know is refused, so that a misspelt one
does not pass for an absent one. `SeasonReader::all()` reads a list of at most 100 seasons; `season()` reads one;
`write()` gives back the array `season()` reads. Every refusal throws `InvalidSeason` with the reason.

`resources/sample-seasons.php` holds sample seasons of a games community — a winter festival, a spooky season, a
harvest feast on the fourth Thursday of November, a spring cleaning that comes once — for a demonstration and a site's
first configuration. Every reference is an example the site replaces with its own.
