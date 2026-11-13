# Formats and rules

## A format

A `Format` declares the rules a deck is built to. The application gives its formats; the library only applies them.

| Rule | Value | By default |
|---|---|---|
| `code`, `name` | The format's code and the name shown to people | Required |
| `minSize` | The smallest deck, 0 to 100 000 cards | 1 |
| `maxSize` | The largest deck, from the smallest (at least 1) to 100 000 cards, or null for no limit | null |
| `copies` | The copies allowed of each card that sets none itself, 1 to 999 | 999 |
| `banned` | The cards the format bans, each once, up to 10 000 | None |
| `restricted` | The cards the format restricts, each with the copies allowed, 1 to 999, up to 10 000 | None |
| `tabooLists` | Dated taboo lists, each code and day once, up to 100 | None |

A `Card` may set its own number of copies, which replaces the format's: `new Card('path', 'Forest Path', copyLimit: 20)`
for a basic card any number of which is played, `copyLimit: 1` for a unique one.

| Value | Shape |
|---|---|
| The code of a format or a taboo list | 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter or a digit |
| The identifier of a card or a deck | 1 to 255 ASCII letters, digits, `_`, `.`, `:`, `/` or `-`, starting with a letter or a digit |
| A name | 1 to 200 characters of UTF-8, without control characters or surrounding spaces |

## Taboo lists

A `TabooList` bans or restricts cards from a day on: the day it takes effect, then its own banned and restricted
cards. On a given day, the taboo list that applies is the latest whose day is that day or before; it replaces the
taboo lists before it, and adds to the format's own lists. A card the taboo list restricts takes the taboo list's
number in place of the format's restriction of the same card.

The day of a taboo list is a date, kept at midnight UTC. The day a deck is checked on is taken in the time zone of the
moment given: 22:00 on 30 September in Montréal is still 30 September, whatever the hour in UTC.

## The rules of a day

`Format::rulingOn($day)` gives the `Ruling` of that day: the format and the taboo list that applies, or none. For each
card, it tells:

- `bannedBy($card)`: what bans it — `the format "league"`, `the taboo list "autumn" from 2026-10-01` —, or null;
- `copyLimit($card)`: the copies allowed — none when the card is banned, otherwise the card's own number or the
  format's, lowered by a restriction; a restriction never raises a limit;
- `restrictedBy($card)`: what restricts it below its own number or the format's, or null.

## Reading a format

`Format::fromArray()` reads the array `toArray()` writes, and `fromJson()` reads JSON of at most 1 MiB, nested at most
15 levels deep. Both refuse an unknown key, a missing key and a value of the wrong type with `InvalidDeck`,
never another exception.

```json
{
    "code": "league",
    "name": "League",
    "min_size": 40,
    "max_size": 60,
    "copies": 3,
    "banned": ["old-relic"],
    "restricted": {"storm-call": 1},
    "taboo_lists": [
        {"code": "autumn", "effective_from": "2026-10-01", "banned": ["night-owl"], "restricted": {"ember-fox": 2}}
    ]
}
```
