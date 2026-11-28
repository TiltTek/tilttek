# Difficulty

The library knows no game: each game system declares how hard an encounter is, as a `Contract\DifficultyRules`. The
rules take the participants of an encounter and give an `Difficulty\Assessment`:

| Value | What it is |
|---|---|
| `cost` | What the adversaries cost, as the rules count it. |
| `bands` | The bands of difficulty, from the easiest, each a key and the threshold — the cost from which the encounter is of that band — for these characters. |
| `difficulty` | The key of the last band whose threshold the cost meets, or null when it meets none. |
| `characters`, `adversaries` | How many characters the thresholds are for, and how many adversaries the cost is for, every copy counted. |

`threshold()` gives a band's threshold, `reached()` whether the cost meets it, and `remainingTo()` what the game master
may still add before the encounter reaches it — 0 once it does. `toArray()` gives it as plain data, to show.

Rules refuse a rating they do not know with `InvalidEncounter`, and an encounter they cannot assess — no character, an
adversary beyond what they rate — with `EncounterRefused`. They take up to 100 participants.

The library brings two kinds of rules that a system fills with its own numbers — usually the difficulty numbers of its
`tilttek/rpg-systems` system ([The system's numbers](#the-systems-numbers)); a system whose rules fit neither is a
class of the application implementing `Contract\DifficultyRules`.

## By budget

`Difficulty\BudgetRules`: each character brings an amount to each band by its level, and each copy of an adversary
costs an amount by its challenge. The thresholds are the sums of the characters' amounts; the cost is the sum of the
adversaries' costs, multiplied by the `Difficulty\Multiplier` that holds for their number, in percent, rounded down.

```php
new BudgetRules(
    'my-system',
    ['low', 'moderate', 'high'],                                          // the bands, from the easiest
    ['1' => ['low' => 50, 'moderate' => 75, 'high' => 100],               // by a character's level
     '2' => ['low' => 100, 'moderate' => 150, 'high' => 200]],
    ['1/4' => 10, '1/2' => 25, '1' => 50, '2' => 100],                    // by an adversary's challenge
    [new Multiplier(2, 150), new Multiplier(4, 200)],                     // ×1.5 from two adversaries, ×2 from four
);
```

Two characters of levels 1 and 2 against three wolves of challenge 1/2 have thresholds of 150, 225 and 300; the wolves
cost 75, ×1.5 for their number: 112, under every band. Allies count neither way.

Each row of budgets gives every band, its amounts growing from the easiest band; the multipliers are in the order of the
number they hold from. Up to 12 bands, 1,000 ratings in each table, 20 multipliers, amounts from 0 to 1,000,000,000.

## By level

`Difficulty\LevelRules`: each band has a budget for a party of a standard size, raised by an adjustment for each
character over that size and lowered for each one under it, never under 0. The party's level is the mean of the
characters' levels, rounded down; each copy of an adversary costs an amount by how many levels it stands above the
party — negative below.

```php
new LevelRules(
    'my-system',
    ['trivial' => 40, 'moderate' => 80, 'severe' => 120],   // the budgets for the standard party, from the easiest
    4,                                                       // the standard size of a party
    ['trivial' => 10, 'moderate' => 20, 'severe' => 30],    // by band, for each character over or under it
    [-2 => 20, -1 => 30, 0 => 40, 1 => 60, 2 => 80],         // by level difference, every one from the lowest to the highest
);
```

An adversary further below the party than the lowest difference costs nothing; one further above the highest is
refused. The budgets grow from the easiest band. The ratings are whole levels, from -99 to 999. Up to 12 bands, a
standard party of 1 to 20, differences from -50 to 50, amounts from 0 to 1,000,000,000.

## The system's numbers

A system of `tilttek/rpg-systems` declares the difficulty of an encounter as its difficulty numbers `encounter`:
their `method`, their bands from the easiest, and their tables. `Difficulty\Declaration::fromSystem()` reads them into
either kind of rules:

| Method | Rules | Tables |
|---|---|---|
| `budget` | `BudgetRules` | `budgets`: by a character's level, an amount for each band; `costs`: by an adversary's challenge, its cost; `multipliers` (optional): by the number of adversaries a multiplier holds from, its percent |
| `level` | `LevelRules` | `party-size`; `budgets` and `adjustments`: by band; `costs`: by level difference |

```php
use Tilttek\Encounter\Difficulty\Declaration;

$rules = Declaration::fromSystem($srd);                 // the numbers `encounter` of the SRD 5.2: by budget
$rules = Declaration::fromSystem($pathfinder);          // those of Pathfinder Second Edition: by level
$rules = Declaration::fromSystem($system, 'threat');    // numbers of another key
```

The import profiles of `tilttek/rpg-systems` state these numbers for the SRD 5.2 — the XP budget of a character by
level for a low, moderate or high difficulty, and the XP of a creature by challenge rating — and for Pathfinder Second
Edition — the budgets of a party of four from trivial to extreme, their character adjustments and the XP of a creature
by its level against the party's. The SRD 5.1 gives no rules of encounter difficulty.

A system without numbers of the key is refused with `UnknownSystem`; numbers of another method, a table the method does
not read or a table it needs missing, with `InvalidEncounter`, and then every check of `read()`.

## Rules as data

An application that brings its own rules — for a system without difficulty numbers, or in the place of its numbers —
gives them as plain data, decoded from JSON or YAML. `Difficulty\Declaration::read()` reads them into either kind of
rules, by its `method`:

```json
{
    "method": "budget",
    "system": "my-system",
    "bands": ["low", "high"],
    "budgets": {"1": {"low": 50, "high": 100}, "2": {"low": 100, "high": 200}},
    "costs": {"1/2": 25, "1": 50},
    "multipliers": [{"from": 2, "percent": 150}]
}
```

```json
{
    "method": "level",
    "system": "my-system",
    "partySize": 4,
    "budgets": {"low": 60, "high": 120},
    "adjustments": {"low": 15, "high": 30},
    "costs": {"-1": 30, "0": 40, "1": 60}
}
```

Every key is required but `multipliers`. A key it does not know, a value of another type, a table too long or a value
out of its bounds is refused with `InvalidEncounter`, before anything is built. The reader is fuzzed.

## The rulebooks

`Rulebooks` holds the rules of each game system the application offers, up to 1,000, one per system. `for()` finds
them by the system's key or by the `GameSystem` of `tilttek/rpg-systems`, and refuses a system without rules with
`UnknownSystem`; `has()` asks without refusing.

`Rulebooks::fromSystems()` reads the rules of every system that declares difficulty numbers `encounter` — of
`Systems` or any list of `GameSystem` —, and takes the rules the application gives: a rule given for a system takes the
place of the one its numbers declare, and gives rules to a system without them.

```php
$rulebooks = Rulebooks::fromSystems($systems);
$rulebooks = Rulebooks::fromSystems($systems, Declaration::RECORD, Declaration::read($houseRules), new MyRules());
```
