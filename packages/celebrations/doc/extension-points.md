# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Default |
|---|---|---|
| `Contract\CelebrationStore` | Where inboxes and preferences are kept | `Store\InMemoryCelebrationStore` in tests |
| `Contract\CelebrationObserver` | What to do with a change of an inbox | None |
| `Limits` | Its bounds on pending entries and entries per member, and entries shown per group | 200, 500 and 10 |
| `Psr\Clock\ClockInterface` | The moment of each act, and of reading what lapsed | — |

## Writing a celebration store

A `CelebrationStore` names members by their references written `kind:identifier`, and an entry by its member, its
source and its occurrence, unique together. It keeps:

- the entries — `find()`, `save()`, which replaces the entry of the same member, source and occurrence, `remove()`,
  `countEntries()`, `entries()`, by sequence, the first received first, all of them or those in one `EntryState`, and
  `latest()`, a page the last received first, before a sequence when one is given;
- the sequences — `nextSequence()`, one more than any the member was given, never reused, even after `remove()` or
  `forget()`: a column of the member's row, or a sequence of the database;
- the preferences — `preferences()`, none when the member chose none, and `savePreferences()`;
- `forget()`, which removes the member's entries and preferences.

The library checks that an entry is new and keeps the inbox's bounds before it writes: an application that serves
concurrent requests runs each act of `Celebrations` in a transaction, or under a lock on the member, and keeps a unique
index on the member, the source and the occurrence, so that an achievement told twice at once is kept once. A
relational store indexes the member with the state and the sequence, which every read filters and orders by.

## Observers

A `CelebrationObserver` is told a `CelebrationChanged` after each act, once the store wrote it. The event holds
identifiers only — the member, the source and the occurrence of the entry — with the `Change` and the moment;
parameters and preferences are never in it. An observer relays the event — the count pushed to the member's open pages,
a message on a bus — and keeps no state between two calls.

| `Change` | Source and occurrence |
|---|---|
| `Received` | The entry received, pending or lapsed already |
| `Seen` | The entry marked seen |
| `Withdrawn` | The entry withdrawn |
| `Lapsed` | The entry lapsed, its moment passed or pushed out by a newer one |
| `PreferencesChanged`, `Forgotten` | — |

An achievement told again is not told: only a new entry is.
