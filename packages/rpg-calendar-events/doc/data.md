# Data

`data/events.php` holds the shipped events, one row per entry, and `data/VERSION` the day they last changed, such as
`2026.11.21`. A row has the fields `EntryReader` reads: `id`, `names`, `rule`, `source`, `community`, `verified`, and
optionally `tags`, `days`, `status` and `prompts`.

## Events

Every rule below was checked against its source on 2026-11-21.

| Identifier | Rule | Community | Tags |
|---|---|---|---|
| `gm-day` | `03-04` | `tabletop-roleplaying` | game-masters, appreciation |
| `rpgaday` | `08-01`, 31 days, a prompt a day | `tabletop-roleplaying` | daily-prompts, writing |
| `nagademon` | `11-01`, 30 days | `game-designers` | game-design, challenge |

GM's Day thanks game masters; its date answers the call to "march forth". RPGaDAY is a month of daily prompts about
roleplaying, published each year by its hosts: the package gives the day's index, never the prompts. National Game
Design Month asks designers to write and play a game in November.

## Sources

Each event's rule comes from the address in its `source` field; the names are the package's own, and no text of a
source is copied.

| Event | Source |
|---|---|
| `gm-day` | <https://sjgames.com/ill/archive/February_29_2012> |
| `rpgaday` | <https://www.autocratik.com/> |
| `nagademon` | <https://itch.io/jam/national-game-design-month-2025> |

## What the data leaves out

- **Conventions.** A convention sets its date each year, often not by a rule: the application adds the conventions it
  knows, as below.
- **Events whose organiser sets the date each year.** A yearly free game day held on a different Saturday from one
  year to the next has no rule to ship; the application adds the year's date as an entry of one day.
- **The prompts of a themed month.** They belong to their authors: the application writes its own, or shows a list it
  has the right to show, by the index `promptDays()` gives.
- **Commercial "national days", and a date a brand declares about itself.**
- **Brand names and logos as the package's mark.** A name appears only where it says which event it is.
- **An event that is not sure.** An entry whose rule its source does not confirm is left out rather than guessed.

## Adding an event

An application adds its own events without changing the shipped file:

```php
$catalog = Catalog::shipped($zone, new EntryReader()->readAll([
    [
        'id' => 'local-con',
        'names' => ['en' => 'Local convention', 'fr' => 'Convention locale'],
        'rule' => '3rd saturday of june',
        'days' => 2,
        'source' => 'https://convention.example/dates',
        'community' => 'montreal',
        'verified' => '2026-11-21',
        'tags' => ['convention'],
    ],
    [
        'id' => 'prompt-month',
        'names' => ['en' => 'Our month of prompts', 'fr' => 'Notre mois d’invites'],
        'rule' => '02-01',
        'days' => 28,
        'prompts' => true,
        'source' => 'https://club.example/prompts',
        'community' => 'our-club',
        'verified' => '2026-11-21',
    ],
]));
```

An identifier the catalog already holds is refused. Check rows read from a file or a form first with
`new DataCheck()->problems($rows)`, which lists every problem instead of stopping at the first.

To propose an event for the shipped data: add its row to `data/events.php` in date order, with a source that states
the rule, `verified` set to the day it was checked, and `data/VERSION` set to that day; then add it to the tables
above. The data test reads every row, computes every event over common and leap years, and fails on any problem.
