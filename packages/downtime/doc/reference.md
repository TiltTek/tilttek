# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Rules

| Type | Role |
|---|---|
| `SystemRules` | The activities the rules of one game system declare, each key once. |
| `Activity` | An activity: its name, days, costs, outcomes and resolution. |
| `Cost` | What an activity costs in one resource, once or for each day. |
| `CostBasis` | Once or per day. |
| `Outcome` | One result an activity may end with: its label, text and effects. |
| `Effect` | A gain or a loss in one resource. |
| `Resolution` | How an activity ends: a fixed outcome, or the band its roll falls in. |
| `Band` | The totals of a roll that end an activity with one outcome. |

## The log

| Type | Role |
|---|---|
| `Downtime` | The service: choose, advance a campaign, record a roll, confirm, cancel, read a log and an entry. |
| `Entry` | One activity in the log of a character, with the days spent, the roll, the outcome and its stage. |
| `Stage` | Queued, awaiting its roll, resolved, confirmed or cancelled. |

## Ports and in-memory implementations

| Type | Role |
|---|---|
| `Contract\EntryStore` | Keeps the entries at a version, all or none, and reads them in the order chosen. |
| `Contract\RuleBook` | The activities the rules of each system declare. |
| `Contract\Roster` | Who runs a campaign and who plays each character. |
| `Contract\Roller` | The total of one roll of a dice expression. |
| `Contract\DowntimeObserver` | Told of the events of the entries. |
| `Rules\InMemoryRuleBook` | The rules of the systems an application declares once. |
| `Store\InMemoryEntryStore` | The entries in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\DowntimeEvent` | Every event: the entry, its campaign and its character. |
| `Event\ActivityChosen` | A character chose an activity. |
| `Event\ActivityProgressed` | Days were spent on an activity, and some remain. |
| `Event\ActivityCompleted` | All the days of an activity were spent, and its roll is awaited. |
| `Event\ActivityResolved` | An activity ended with an outcome. |
| `Event\ActivityConfirmed` | The game master confirmed the outcome. |
| `Event\ActivityCancelled` | An activity was cancelled. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\DowntimeException` | Every exception of the library. |
| `Exception\InvalidDowntime` | A value out of its shape or its bounds, or parts that do not agree. |
| `Exception\UnknownActivity` | The rules of the system declare no activity of the key. |
| `Exception\DowntimeRefused` | A change the service does not allow. |
| `Exception\EntryNotFound` | No entry has the identifier. |
| `Exception\DowntimeConflict` | An entry changed since it was read. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of an identifier and of a key, and the first value of a list that comes twice. |
| `Internal\Text` | One line or lines of text, checked and bounded. |
