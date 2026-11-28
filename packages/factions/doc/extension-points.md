# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the rosters and their history | Implement `Contract\RosterStore`, and pass it to `Factions`. |
| Date the changes | Pass a `Psr\Clock\ClockInterface` to `Factions`. |
| Act on what happened | Implement `Contract\RosterObserver`, and pass it to `Factions`. |
| Use another scale | Build a `Scale` with its own bounds, start and tiers; no code is needed. |

## The store

`Contract\RosterStore` keeps the rosters, each at a version, with their history:

- `roster()` gives the roster of a campaign, or null;
- `save()` keeps a roster at the next version, with the entries of the history the change makes, in one write — one
  transaction — and returns it. A roster at version 0 is new, and the store has none for its campaign; any other is kept
  only when the store holds it at that version, and `RosterConflict` is raised otherwise, nothing kept, entries included;
- `delete()` forgets a roster and its whole history when the store holds it at its version, and raises
  `RosterConflict` otherwise, nothing forgotten;
- `history()` gives the entries of a campaign that concern a faction, the newest first, at most the limit, and raises
  `InvalidRoster` for a limit below 1. An entry concerns its `faction`, and a `History\RelationEntry` also its `other`;
  a store reads them by an index on the campaign and those two keys, the newest first. It keeps the entries of a faction
  removed from the roster.

`Store\InMemoryRosterStore` keeps them in memory, as long as the object lasts: it is for tests. An application keeps
them in its database:

- a roster holds strings, an integer, its scale — three integers and a list of tiers of a key, a label and an integer —,
  its factions and its relations, and fits a JSON column, or rows of their own kept in the order given;
- a faction holds strings, a boolean, a list of ranks of a key and a title, standings of a subject key and an integer,
  and memberships of two strings;
- a relation holds two keys, an enumeration, a note and a boolean;
- an entry holds strings, integers or nullable relations, a reason of a text and a nullable session identifier, and a
  date.

The store rebuilds each value with its constructor — `Subject::fromKey()` for a subject —, which checks it again.

## The clock

The service reads `ClockInterface::now()` once for each entry of the history it makes. A test passes a clock it sets,
such as the `MockClock` of `symfony/clock`.

## The observer and its events

`Contract\RosterObserver::notify()` is told each fact once the roster is kept, by identifiers only:

| Event | When | Carries |
|---|---|---|
| `Event\RosterCreated` | A roster was created. | |
| `Event\FactionAdded` | A faction was added. | the faction's key |
| `Event\FactionChanged` | Its name, summary, ranks or secrecy changed. | the faction's key |
| `Event\FactionRemoved` | A faction was removed, with its relations. | the faction's key |
| `Event\ScaleChanged` | The scale changed. | |
| `Event\StandingChanged` | A standing moved. | the faction's key, the subject's key |
| `Event\RankChanged` | A character joined, rose, fell or left. | the faction's key, the character |
| `Event\RelationChanged` | A relation was set, changed or ended. | the pair, the lower key first |
| `Event\RosterHandedOver` | It was handed to another game master. | the new game master |
| `Event\RosterDeleted` | It was deleted, with its history. | |

Every event implements `Event\RosterEvent`, whose `campaign` is an identifier. An application sends a message from them
— through Messenger, with the identifiers alone — and reads the rest from the store: on `StandingChanged`, the pages of
the players reload their standings, and a message never holds a reason, a note or a summary.
