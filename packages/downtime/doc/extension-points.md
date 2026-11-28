# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the entries | Implement `Contract\EntryStore`, and pass it to `Downtime`. |
| Declare the activities of each system | Pass `Rules\InMemoryRuleBook`, or implement `Contract\RuleBook`. |
| Say who runs a campaign and who plays a character | Implement `Contract\Roster`. |
| Roll the dice of the systems | Implement `Contract\Roller`, or pass nothing to record the rolls made at the table. |
| Act on what happened | Implement `Contract\DowntimeObserver`, and pass it to `Downtime`. |

## The store

`Contract\EntryStore` keeps the entries, each at a version, and reads them in the order they were chosen:

- `entry()` gives one by its identifier, or null;
- `save()` keeps a list of entries at their next version, all of them or none, and returns them in the order given. One
  at version 0 is new, and the store has none of its identifier; any other is kept only when the store holds it at
  that version, and `DowntimeConflict` is raised otherwise, nothing kept. An entry given twice in one call is kept at
  each of its versions in turn;
- `entriesOf()` gives the log of a character in a campaign, `openOf()` its open entries — queued, awaiting a roll or
  resolved —, and `queuedIn()` the queued entries of a campaign, every character's: all in the order chosen, at most
  the limit, and `InvalidDowntime` for a limit below 1.

"The order chosen" is the order the entries were first kept: a store keeps a sequence column, or a creation timestamp
with the identifier to break ties, and reads by an index on the campaign, the character and the stage.

`Store\InMemoryEntryStore` keeps them in memory, as long as the object lasts: it is for tests. An application keeps
them in its database, in one transaction per `save()`. An entry holds strings, integers, an enumeration and its
activity, whose costs, outcomes, effects and bands are strings, integers and enumerations that fit a JSON column. The
store rebuilds the activity with `new Activity()` and the entry with `new Entry()`, which check them again.

## The rule book

`Contract\RuleBook::activity()` gives the activity of a key in the rules of a system, or null; `activities()` lists
those a system declares, in its order, for the page where a player chooses. `Rules\InMemoryRuleBook` holds the
`SystemRules` an application declares once — from its configuration, or from files of its own — and shares between
requests. An application whose game masters write their own activities implements the rule book over its own query.

The service reads the rule book only when an activity is chosen: the entry keeps the activity whole.

## The roster

`Contract\Roster::isGameMaster()` says whether a member runs a campaign, and `plays()` whether a member plays a
character in it. The service asks the first before the second, once per call; they answer from the session, from
memory, or from one indexed read.

## The roller

`Contract\Roller::roll()` gives the total of one roll of a dice expression the rules declare. The service calls it when
an advance spends the last day of a rolled activity, once per entry; it keeps the total within ±1 000 000. A roller
reads the expressions of its systems — with a dice library of the application's choice — and draws from a
cryptographically secure source. Without a roller, the entry waits for `record()`.

## The observer and its events

`Contract\DowntimeObserver::notify()` is told each fact once the entries are kept, by identifiers only:

| Event | When |
|---|---|
| `Event\ActivityChosen` | A character chose an activity: debit its costs. |
| `Event\ActivityProgressed` | An advance spent days on an activity, and some remain. |
| `Event\ActivityCompleted` | An advance spent the last day of an activity, which waits for its roll. |
| `Event\ActivityResolved` | An activity ended with its outcome, by an advance or by a roll recorded. |
| `Event\ActivityConfirmed` | The game master confirmed the outcome: apply its effects. |
| `Event\ActivityCancelled` | An activity was cancelled: refund what the rules refund. |

Every event implements `Event\DowntimeEvent`, whose `entry`, `campaign` and `character` are identifiers. An application
sends a message from them — through Messenger, with the identifiers alone — and reads the rest from the store: on
`ActivityCompleted`, the player is asked for their roll. An advance tells one event per entry it changed, in the order
chosen.
