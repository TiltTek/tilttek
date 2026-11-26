# Data

`data/dates.php` holds the shipped entries, one row per entry, and `data/VERSION` the day they last changed, such as
`2026.11.21`. A row has the fields `EntryReader` reads: `id`, `names`, `rule`, `source`, `community`, `verified`, and
optionally `tags`, `days` and `status`.

## Entries

Every rule below was checked against its source on 2026-11-21.

| Identifier | Rule | Community | Tags |
|---|---|---|---|
| `tolkien-birthday-toast` | `01-03` | `tolkien-readers` | books, fantasy |
| `pi-day` | `03-14` | `worldwide` | mathematics, science |
| `tolkien-reading-day` | `03-25` | `tolkien-readers` | books, fantasy |
| `world-backup-day` | `03-31` | `worldwide` | computing |
| `yuris-night` | `04-12` | `worldwide` | space, science |
| `may-the-fourth` | `05-04` | `star-wars-fans` | films, science-fiction |
| `towel-day` | `05-25` | `douglas-adams-readers` | books, science-fiction, humour |
| `geek-pride-day` | `05-25` | `worldwide` | geek-culture |
| `tau-day` | `06-28` | `worldwide` | mathematics, science |
| `asteroid-day` | `06-30` | `worldwide` | space, science |
| `sysadmin-day` | `last friday of july` | `worldwide` | computing |
| `programmers-day` | `day 256` | `worldwide` | computing |
| `software-freedom-day` | `3rd saturday of september` | `worldwide` | computing, free-software |
| `talk-like-a-pirate-day` | `09-19` | `worldwide` | humour |
| `hobbit-day` | `09-22` | `tolkien-readers` | books, fantasy |
| `world-space-week` | `10-04`, 7 days | `worldwide` | space, science |
| `ada-lovelace-day` | `2nd tuesday of october` | `worldwide` | science, computing, women-in-science |
| `mole-day` | `10-23` | `chemistry-teachers` | chemistry, science |

`day 256` is September 13, or September 12 in a leap year. `may-the-fourth` is named after the fans' pun, the day
they keep, not after the brand.

## Sources

Each entry's rule comes from the address in its `source` field; the names are the package's own, and no text of a
source is copied. The sources are:

| Entries | Source |
|---|---|
| `tolkien-birthday-toast`, `tolkien-reading-day` | <https://en.wikipedia.org/wiki/The_Tolkien_Society> |
| `pi-day` | <https://en.wikipedia.org/wiki/Pi_Day> |
| `world-backup-day` | <https://en.wikipedia.org/wiki/World_Backup_Day> |
| `yuris-night` | <https://en.wikipedia.org/wiki/Yuri%27s_Night> |
| `may-the-fourth` | <https://en.wikipedia.org/wiki/Star_Wars_Day> |
| `towel-day` | <https://en.wikipedia.org/wiki/Towel_Day> |
| `geek-pride-day` | <https://en.wikipedia.org/wiki/Geek_Pride_Day> |
| `tau-day` | <https://en.wikipedia.org/wiki/Tau_Day> |
| `asteroid-day` | <https://en.wikipedia.org/wiki/Asteroid_Day> |
| `sysadmin-day` | <https://en.wikipedia.org/wiki/System_Administrator_Appreciation_Day> |
| `programmers-day` | <https://en.wikipedia.org/wiki/Programmers%27_Day> |
| `software-freedom-day` | <https://en.wikipedia.org/wiki/Software_Freedom_Day> |
| `talk-like-a-pirate-day` | <https://en.wikipedia.org/wiki/International_Talk_Like_a_Pirate_Day> |
| `hobbit-day` | <https://en.wikipedia.org/wiki/Hobbit_Day> |
| `world-space-week` | <https://en.wikipedia.org/wiki/World_Space_Week> |
| `ada-lovelace-day` | <https://en.wikipedia.org/wiki/Ada_Lovelace_Day> |
| `mole-day` | <https://en.wikipedia.org/wiki/Mole_Day> |

## What the data leaves out

- **Commercial "national days".** The lists of a calendar service are its own content, often promotional: none is
  copied.
- **A date a brand declares about itself.** A company's day for its own product is its marketing, not a date of the
  community.
- **Brand names and logos as the package's mark.** A name appears only where it says which day it is.
- **Convention dates.** A convention sets its date each year: the application adds it, as below.
- **A date that is not sure.** An entry whose rule its source does not confirm is left out rather than guessed.

## Adding an entry

An application adds its own entries without changing the shipped file:

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
        'status' => 'to-verify',
    ],
]));
```

An identifier the catalog already holds is refused. Check rows read from a file or a form first with
`new DataCheck()->problems($rows)`, which lists every problem instead of stopping at the first.

To propose an entry for the shipped data: add its row to `data/dates.php` in date order, with a source that states
the rule, `verified` set to the day it was checked, and `data/VERSION` set to that day; then add it to the tables
above. The data test reads every row, computes every entry over common and leap years, and fails on any problem.
