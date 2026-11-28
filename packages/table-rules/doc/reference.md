# Reference

Every public type of the package, with its role. The types marked internal may change in any version.

## Rules and versions

| Type | Role |
|---|---|
| `Rule` | One house rule: identifier, title, text and category. |
| `RulebookVersion` | One published version: number, rules, variants, system, note, author, moment and whether it requires acceptance. |
| `Rulebook` | Where a table's rules stand: the current version, the baseline, each player's last decision and the revision. |
| `Acceptance` | A player's last decision: the version, accepted or declined, and when. |
| `Decision` | Accepted or declined. |
| `AcceptanceState` | Pending, accepted, outdated or declined. |
| `TableRules` | The service: publish, accept, decline, forget, version, status, roster and comparison. |

## What changed

| Type | Role |
|---|---|
| `Diff\RulebookDiffer` | Compares two versions. |
| `Diff\RulebookDiff` | The rules added, removed, changed and moved, the variants chosen and dropped, the system before and after. |
| `Diff\RuleChange` | A rule of both versions with another title, text or category, and the lines of its text. |
| `Diff\LineChange` | One line of a text, kept, removed or added. |
| `Diff\LineKind` | Kept, removed or added. |

## Views

| Type | Role |
|---|---|
| `View\PlayerStatus` | Where a player stands, and what changed since the version answered. |
| `View\RosterView` | Where the players named stand, and how many are in each state. |

## Ports and stores

| Type | Role |
|---|---|
| `Contract\RulebookStore` | Keeps each table's rulebook at a revision, and every version published. |
| `Contract\TableRulesObserver` | Told of each fact once the rulebook is kept. |
| `Contract\VariantCatalogue` | Says whether a game system offers a variant. |
| `Catalogue\SystemVariantCatalogue` | The catalogue of the rule variants of the `tilttek/rpg-systems` systems. |
| `Store\InMemoryRulebookStore` | The rulebooks in memory, for tests. |

## Events

| Type | Role |
|---|---|
| `Event\RulesEvent` | A fact of a table's rules, by identifiers only. |
| `Event\RulebookPublished` | A new version was published. |
| `Event\RulesAccepted` | A player accepted the version that holds. |
| `Event\RulesDeclined` | A player declined the version that holds. |
| `Event\PlayerForgotten` | The decision of a player was forgotten. |

## Exceptions

| Type | Role |
|---|---|
| `Exception\TableRulesException` | Every exception of the library. |
| `Exception\InvalidRules` | A value out of its shape or its bounds. |
| `Exception\RulesRefused` | A change not allowed on the rulebook in its state. |
| `Exception\RulesNotFound` | No rules published, or no version of the number. |
| `Exception\RulesConflict` | The rulebook changed since it was read. |

## Internal

| Type | Role |
|---|---|
| `Internal\Identifier` | The shape of every identifier. |
| `Internal\Text` | The shape of titles, texts and notes, and their length. |
| `Internal\Lcs` | The longest common subsequence of two lists of strings. |
