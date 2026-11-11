# Badges and criteria

## A badge

`Badge` holds:

| Field | Meaning |
|---|---|
| `code` | 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter: `commenter.gold` |
| `name`, `description` | Texts, or translation keys the application translates; a name of 1 to 255 characters, a description of at most 1,020 |
| `image` | Where its image is, as the application writes it; `null` for none |
| `criterion` | What earns it |
| `repeatable` | Earned again each time its target is reached again: 23 comments for a badge of 10 are two awards |
| `hidden` | A secret achievement: left out of a member's overview, criterion included, until they earn it |
| `revocable` | Withdrawn when the activity that earned it is cancelled |
| `tier` | Its series and rank: bronze is rank 1 of the series `commenter`, silver rank 2 |

Each badge of a tier has its own criterion and threshold; `Catalog::series('commenter')` lists them by rank.

## Criteria

A criterion is a tree. Its leaves read the activity — each activity a leaf accepts brings it a `Contribution` — and a
combination reads the progress of its children.

| Criterion | Example | Progress |
|---|---|---|
| `Count` | Have commented on 10 news items | The activities accepted |
| `Count` of 1 — *first* | Have published a first sheet | 0 or 1 of 1 |
| `Total` | Have read 1,000 pages | The quantities of the activities, summed |
| `Streak` | Have come 7 days running | The longest run of days with at least one activity, in the catalog's timezone |
| `Measure` | Have 1,000 points | The value the application measures, read when one of the scope's activities happens |
| `Manual` | Awarded by an administrator | Never reached by activity |
| `AtLeast` | All of a list, or 2 of 3 | The criteria reached |

A *period* — *have taken part during the event's month* — is a leaf whose `Scope` has a `since`, included, and an
`until`, excluded. A leaf's `Scope` also names its verbs by pattern (`game.played`, `game.*`) and the facts the
activity's context must hold (`system: dnd5e`).

`Measure` is how a badge reads a value another package keeps, such as a balance of points, without depending on it:
the application implements `Measures` and passes it to the `Evaluator`. The value read is the current one, not the one
at the activity's moment.

## The catalog from plain values

`Catalog::fromArray()` reads badges as a configuration holds them, and `CriterionReader` their criteria:

```php
$catalog = Catalog::fromArray([
    'commenter.bronze' => [
        'name' => 'badges.commenter.bronze.name',
        'description' => 'badges.commenter.bronze.description',
        'image' => '/images/badges/commenter-bronze.svg',
        'tier' => ['series' => 'commenter', 'rank' => 1],
        'revocable' => true,
        'criterion' => ['count' => ['verbs' => ['news.commented'], 'times' => 10]],
    ],
    'reader' => ['repeatable' => true, 'criterion' => ['total' => ['verbs' => ['book.read'], 'amount' => 1000]]],
    'regular' => ['criterion' => ['streak' => ['verbs' => ['site.visited'], 'days' => 7]]],
    'event' => ['criterion' => ['first' => ['verbs' => ['event.*'], 'since' => '2026-12-01', 'until' => '2027-01-01']]],
    'rich' => ['criterion' => ['measure' => ['name' => 'points', 'threshold' => 1000, 'verbs' => ['game.*']]]],
    'organiser' => ['criterion' => ['manual' => true]],
    'all-round' => ['criterion' => ['at_least' => ['count' => 2, 'of' => [
        ['first' => ['verbs' => ['game.played']]],
        ['first' => ['verbs' => ['news.commented']]],
        ['first' => ['verbs' => ['sheet.published'], 'context' => ['system' => 'dnd5e']]],
    ]]]],
], 'America/Toronto');
```

The timezone is the one the days of a streak and the dates of a period are read in. A name left out is the code. Every
mistake is refused with an `InvalidBadge` that says what is wrong: a code, a kind of criterion, a verb, a context value,
a date, a rank held twice, a criterion nested so deep that a path passes 64 bytes.
