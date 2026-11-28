# Downtime

## Activities

The rules of a game system declare what a character may do between sessions. An `Activity` is found by its key within
the rules of its system, and holds:

| Part | Holds |
|---|---|
| `name`, `description` | One line, and lines the players read, possibly empty. |
| `days` | The days it takes, 1 to 365. |
| `costs` | Up to 8 `Cost`: a resource the system names — gold pieces, supplies, a favour — and an amount, paid once (`new Cost()`) or for each day (`Cost::perDay()`). |
| `outcomes` | 1 to 16 `Outcome`: a key, a label, a text, and up to 8 `Effect`, each a gain or a loss in a resource. |
| `resolution` | How one outcome is chosen: a `Resolution`. |

`Activity::totals()` gives what it costs over its days, by resource. The library counts costs and effects; the
application debits and applies them, in the resources it keeps.

An activity is checked as a whole when it is built: each outcome once, each cost's resource once, each effect's
resource once within its outcome, and every outcome the resolution names declared.

`SystemRules` holds the activities of one system, 1 to 64, each key once, in the order given.

| Bound | Value |
|---|---|
| Activities of a system | 64 (`SystemRules::MAX_ACTIVITIES`) |
| Days of an activity | 1 to 365 (`Activity::MAX_DAYS`) |
| Outcomes of an activity | 1 to 16 (`Activity::MAX_OUTCOMES`) |
| Costs of an activity, effects of an outcome | 8 (`Activity::MAX_COSTS`, `Outcome::MAX_EFFECTS`) |
| Amount of a cost | 1 to 1 000 000 (`Cost::MAX_AMOUNT`) |
| Change of an effect | −1 000 000 to 1 000 000, never 0 (`Effect::MAX_DELTA`) |
| Name of an activity, label of an outcome | one line of 1 to 120 characters |
| Description of an activity, text of an outcome | 4000 and 2000 characters |
| Identifier of an entry, a campaign, a character, a member or a system | 1 to 128 letters, digits, `_`, `.`, `:` or `-`, starting with a letter |
| Key of an activity, an outcome or a resource | 1 to 64 of the same characters, starting with a letter |

The texts are valid UTF-8, trimmed and counted in characters. A line holds no control character; a text keeps its line
breaks and tabulations, its line ends written `\n`. Neither holds the characters that reverse the direction of a text.

## Resolutions

| Built with | Ends with |
|---|---|
| `Resolution::fixed('learned')` | Always that outcome. |
| `Resolution::rolled('1d20+2', $bands)` | The outcome of the `Band` the total falls in. |

A band covers the totals from `from` to `to`, inclusive, between −10 000 and 10 000. A roll has 1 to 16 bands, given
in any order; sorted, they follow one another without overlap nor gap, and the same outcome may come in several of
them. A total below the first band falls in the first, one above the last in the last: a modifier never leaves a roll
without an outcome.

The dice expression is the system's own: up to 64 letters, digits, spaces and the signs `+ - * / ( ) ! < > = . ,`,
without a space at either end. The library never reads it; the application's `Contract\Roller` does.

## The log and the queue

An `Entry` is one activity in the log of a character: its campaign, its character, the member who chose it, the system,
the activity as it was declared then, the days spent, the roll, the outcome, the `Stage` and the version. Rules changed
later never change an entry.

| Stage | The entry |
|---|---|
| `Queued` | Has days still to spend. |
| `AwaitingRoll` | Has spent all its days, and waits for the total rolled at the table. |
| `Resolved` | Has its outcome, and waits for the game master. |
| `Confirmed` | Has its outcome confirmed: the application applies its effects. |
| `Cancelled` | Was cancelled, with the days spent, the roll and the outcome it had. |

The first three stages are open: a character has at most 8 open entries (`Downtime::MAX_QUEUE`), worked on in the order
they were chosen.

## The service

`Downtime` is the only way to change an entry. The `Contract\Roster` says who may act:

| Call | Who | Does |
|---|---|---|
| `choose()` | The player of the character, or the game master | Writes an activity of the rules in the log, queued after the open entries. |
| `advance()` | The game master | Spends 1 to 365 days on every queue of the campaign. |
| `record()` | The player, or the game master | Records the total rolled at the table for an entry awaiting its roll. |
| `confirm()` | The game master | Confirms the outcome, or the one they choose instead. |
| `cancel()` | The player while queued; the game master at any open stage | Cancels the entry. |
| `log()` | The player, or the game master | Reads the entries of a character, in the order chosen, up to 1000. |
| `entry()` | Anyone the application lets read | Reads one entry by its identifier. |

### Advancing a campaign

`advance()` reads the queued entries of the campaign, every character's, in the order chosen, up to 1000; a campaign
with more is refused. It gives each character the days of the advance, spent on the character's queue in order: an
entry takes the days it still needs, and the days left go to the next one. An entry whose days are all spent:

- ends with its outcome when its resolution is fixed;
- ends with the outcome of its band when the service has a roller, the total kept within ±1 000 000;
- waits for its roll otherwise.

Every entry it changes is kept at once, or none: a store that finds one changed meanwhile raises `DowntimeConflict`,
and the advance is made again on what is read anew. It returns the entries changed, in the order chosen.

### Confirming

`confirm()` takes a resolved entry, or an entry awaiting its roll when the game master names the outcome: they settle it
without a roll. An outcome they name is one of the activity's. The roll recorded, if any, stays in the log.

### Costs and effects

The library never debits nor applies anything. An application debits the costs when it is told `ActivityChosen`,
refunds what its rules refund on `ActivityCancelled`, and applies the effects of the outcome on `ActivityConfirmed`,
reading the entry from the store. `Entry::costs()` gives the costs over the activity's days; `Entry::result()` gives
the outcome.

### Concurrency

Each change is made on the version read. `choose()` counts the open entries of the character before keeping a new one;
two choices made at the same moment may both pass the count, and a store that must hold the bound exactly checks it
when it keeps a new entry.
