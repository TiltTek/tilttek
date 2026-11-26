# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Entry` | One date: identifier, names by locale, rule, source, community, date of verification, tags, days, status. |
| `Status` | `Verified` or `ToVerify`: whether the rule was checked against its source. |
| `EntryReader` | Reads entries from arrays: the shipped data's rows and an application's. |
| `Catalog` | The entries in one time zone: the dates of a year, a period or a day. |
| `Filter` | Which entries a question reads: tags, communities, verified only. |
| `Occurrence` | A date the catalog computed: its entry and its span of days. |
| `ShippedData` | The rows, the entries and the version of the shipped data. |
| `DataCheck` | Every problem of a set of rows. |
| `Exception\GeekDatesException` | The interface of every exception of the package. |
| `Exception\InvalidEntry` | An entry that cannot be read, or that a catalog already holds. |
| `Exception\InvalidQuery` | A year out of range, an unknown entry, a filter on nothing. |

## Entries

An `Entry` checks what it is given and refuses anything else with an `InvalidEntry` that names it:

| Field | Bounds |
|---|---|
| `id` | A lowercase letter or digit, then up to 63 of them or `-`: `towel-day`. Stable: an application keys its own records by it. |
| `names` | By locale, `en` and `fr` included, at most 32 locales such as `fr` or `fr_CA`; each name 1 to 200 characters, not blank. |
| `rule` | A `Phil\Almanac\Rule\Rule`; `EntryReader` reads its text with `RuleParser`. |
| `source` | An `https` address on a host name, read by `phil/url` and kept in its canonical form — lowercase host, default port dropped, `.` and `..` resolved —, of at most 2048 bytes, without spaces or credentials: where the rule was checked. |
| `community` | The country or community that keeps the day, as a slug: `worldwide`, `tolkien-readers`. |
| `verified` | The day the rule was last checked against its source. |
| `tags` | At most 16, each once, each a slug. |
| `days` | 1 to 366: World Space Week lasts 7. |
| `status` | `Status::Verified` by default; `Status::ToVerify` for a rule not yet checked. |

`name($locale)` gives the name in the closest locale: `fr_CA`, then `fr`, then English. `definition()` is the
`Phil\Almanac\Definition` the almanac computes: the identifier as key, the English name as title.

## The catalog's questions

`new Catalog($entries, $zone)` refuses an identifier given twice; `Catalog::shipped($zone, $own)` gives the shipped
entries followed by the application's own, and `with(...$entries)` a new catalog with more entries. `entries()`,
`has($id)` and `entry($id)` read them.

| Question | Gives |
|---|---|
| `year(2026)` | The dates that touch the year. |
| `between($from, $until)` | The dates that touch a period, its start included and its end not. |
| `on($day)` | The dates that cover a day, the day read in the catalog's time zone: the object of the day. |

Each takes an optional `Filter`: `new Filter(tags: ['science'])` keeps the entries with one of the tags,
`communities: ['worldwide']` those of one of the communities, `verifiedOnly: true` those of `Status::Verified`. A
filter on an empty list of tags or communities is an `InvalidQuery`: it would read nothing.

The dates come by start, then English name, as `Occurrence`s: `entry`, `span` (a `Phil\Almanac\DateSpan`),
`start()` and `end()` — midnights of the first and last day in the catalog's time zone — and `source()`. Compare them
with `<` and `>`, or with the span's `overlaps()`; never with `==`. A question reads the years 1000 to 3000: a period
from `1000-01-01` until `3001-01-01` at most, or an `InvalidQuery`.

## Data and checks

`ShippedData::rows()` gives the rows of `data/dates.php`, `entries()` the entries read from them, `version()` the
content of `data/VERSION` and `directory()` the folder. `new DataCheck()->problems($rows)` lists every problem of a set
of rows, counted from 1, and an empty list when all are entries.
