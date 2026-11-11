# Tracks and levels

## Curves

A `Curve` says the experience each level starts at. Level 1 starts at 0; each next level starts higher than the one
before, at most at 10^15, and a curve has 2 to 1000 levels.

| Curve | Levels 2, 3, 4… start at | Example |
|---|---|---|
| `Curve::linear($step, $levels)` | `step`, `2 × step`, `3 × step`… | `Curve::linear(100, 50)` |
| `Curve::quadratic($base, $levels)` | `base`, `4 × base`, `9 × base`… | `Curve::quadratic(50, 60)` |
| `Curve::exponential($base, $factor, $levels)` | each step `factor` times the one before, the first `base`, rounded | `Curve::exponential(100, 1.5, 40)` — 100, 250, 475… |
| `Curve::table($thresholds)` | the thresholds the application writes | `Curve::table([300, 900, 2700])` |

The number of levels defaults to 100. `$curve->level($experience)` returns a `Level`: its `number`, the
`experience`, the `floor` it started at, the `next` threshold — null at the last level —, and `gained()`, `span()`,
`missing()` and `percent()` for a progress bar. A table is how a game's own experience thresholds are written.

## Rules

An `ExperienceRule` gives experience for a gesture: the verbs it reads, as patterns such as `game.played` or
`game.*`, the experience per unit of the activity's quantity — or per activity, with `perQuantity: false` —, a `Cap`
per subject over a `Period` — a day, an ISO week from Monday, a month, a year, or ever — and the facts the activity's
context must hold.

```php
new ExperienceRule('runs', ['game.ran'], 50, new Cap(200, Period::Day), ['role' => 'gm']);
```

The periods of a cap are read in the timezone of `Tracks`. A cap that leaves less than the rule gives earns what is
left; a cap reached earns nothing, and the gesture still counts for the streaks.

## Tracks

A `Track` holds its rules, its curve, whom its experience goes to, its seasons and its milestones:

- `Subject::Member`, the default, gives the experience to the member who made the gesture;
- `Subject::Object` gives it to the gesture's object — a character, whose experience the game master awards by an
  activity `character.awarded` with the amount as its quantity —; an activity without an object brings nothing to such
  a track.

Several tracks may read the same verbs: one activity then brings experience to each. Experience is never spent and
never written by hand; it comes from the activity, and a cancellation takes it back.

## From a configuration

```php
$tracks = Tracks::fromArray([
    'player' => [
        'curve' => ['type' => 'quadratic', 'base' => 50, 'levels' => 60],
        'subject' => 'member',
        'seasons' => ['length' => 'quarter'],
        'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 20, 'cap' => ['amount' => 100, 'period' => 'day']]],
        'milestones' => ['veteran' => ['level' => 10], 'thousand' => ['experience' => 1000]],
    ],
    'character' => ['curve' => ['type' => 'table', 'thresholds' => [300, 900, 2700]], 'subject' => 'object', 'rules' => [
        'awarded' => ['verbs' => ['character.awarded'], 'experience' => 1],
    ]],
], 'America/Toronto');
```

A curve type, a subject, a period, a season length or a date that is none, or a curve missing its step, base, factor
or thresholds, raises `InvalidProgression`.

## Standings

`$progression->standing($subject, 'player')` returns a `Standing`: the track, the season under way — none on a track
without seasons or between two named seasons —, the `Level` of the experience that counts — the season's, or all of it
on a track without seasons — and the `lifetime` experience of every season together. `standings($subject)` returns one
per track, by name.
