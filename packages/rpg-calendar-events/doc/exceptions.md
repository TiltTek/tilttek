# Exceptions

Every exception of the package implements `Tilttek\RpgCalendarEvents\Exception\RpgCalendarEventsException`. A
program that shows the catalog catches that interface; one that loads rows of its own reads them with `DataCheck`
first, which lists every problem instead of throwing at the first.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidEntry` | A row or an `Entry` is refused — an unknown field, a rule that does not read, a source that is not an https address on a host name, a name, tag, community or number of days out of bounds, a prompt a day on a single day —, or a catalog is given an identifier twice | `The entry "rpgaday" has no source: an https address on a host name, of at most 2048 bytes, without spaces.` |
| `InvalidQuery` | A year outside 1000 to 3000, a period that leaves those years, an identifier the catalog does not hold, or a filter on an empty list of tags or communities | `The catalog reads the years 1000 to 3000, not 3001.` |

Both extend `InvalidArgumentException`. The message of an `InvalidEntry` names the entry by its identifier, cut at 100
characters, or `?` when the row has none; text taken from a row — an unknown field, a locale, a tag — is cut as well,
so a message stays short whatever the row holds.

`DataCheck::problems()` turns each `InvalidEntry` of a row into a line, `Row 3: The entry "gm-day" …`, and adds a line
for an identifier found twice: `Row 5: the identifier "gm-day" is already row 3.`
