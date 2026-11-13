# Settings

A table's `TableSettings` say what it chose:

| Setting | Default | Meaning |
|---|---|---|
| `tools` | every tool | the tools the table uses; the others take no answer and raise no signal |
| `namedSignals` | `false` | whether a signal says who raised it |
| `signalsSeenBy` | `Audience::Table` | who sees the X-card and Script Change: the table, or the game master alone |
| `customThemes` | none | the themes the table adds, by key, with their labels |
| `sessionZero` | the `campaign` template | the table's session zero |

```php
$safety->configure($table, TableSettings::defaults()
    ->withTools([SafetyTool::LinesAndVeils, SafetyTool::XCard, SafetyTool::OpenDoor])
    ->withSignalsSeenBy(Audience::GameMaster)
    ->withCustomThemes(['table-clowns' => 'Clowns']));
```

`configure()` checks the added themes against the catalogue. A table that kept no settings uses the defaults given to
`TableSafety`'s constructor, or `TableSettings::defaults()`.

`toArray()` and `fromArray()` store the settings as JSON.

## The tools

`SafetyTool` lists them in the order of a game — lines and veils, the consent checklist, session zero, the X-card,
Script Change, the open door, check-ins, stars and wishes. `credit()` names the people or the community each comes from,
and `signals()` the signals it raises.

Naming the tools of a table on its announcement is recommended, never required: an application shows the list and lets
the game master leave it empty.
