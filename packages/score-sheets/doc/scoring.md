# Scoring

## Entries

`Entries` holds the participants of a match — 1 to 100, each once, in the order the sheet shows them —, its teams —
up to 50, each member a participant of one team at most — and the values entered so far. It is a value:

```php
$entries = new Entries([$ada, $bo], [new Team(new TeamId('harbour-masters'), [$ada, $bo])]);
$entries = $entries->with($sheet, $ada, 'boats', 5);     // entered, or corrected
$entries = $entries->without($sheet, $ada, 'boats');     // erased, as if never entered
```

`with()` refuses a participant or a category the sheet does not know, a computed category, and a value out of its
bounds, with `InvalidEntry`. A value not entered counts as 0; `isComplete()` says whether every participant has a
value in every entered category.

Participant, team and match identifiers are opaque: 1 to 64 ASCII letters, digits, dots, underscores and hyphens,
starting with a letter or a digit. They map to the application's accounts and never hold a name.

## The scoreboard

`Scoring\Scorer::score($sheet, $entries)` computes everything again, at each entry:

1. Each category in the declared order: an entered value, or the operation over the values computed before it.
2. The total of each participant: the sum of the categories that count.
3. The ranks: the highest total first, then the tie-breakers in order; participants still equal share their rank,
   and the ranks they fill are skipped — 1, 1, 3.
4. The score of each team, ranked the same way by its total.

```php
$board = $scorer->score($sheet, $entries);

foreach ($board->participants as $score) {           // in rank order
    echo $score->rank, ' ', $score->participant->value, ' ', $score->total, "\n";
}
```

The total of a participant is always the sum of the values of the categories that count; the tests check it over
random entries.

## Explanations

Each `ParticipantScore` carries one `Step` by category: the operation, what it read, and the value. Categories are
named by their key; the constants of the declaration by a name starting with `#`, which no key can take.

```php
['category' => 'set_points', 'operation' => 'product', 'operands' => ['sets' => 2, '#factor' => 4], 'value' => 8]
['category' => 'fleet', 'operation' => 'majority', 'operands' => ['boats' => 5, '#place' => 2], 'value' => 3]
['category' => 'lighthouse', 'operation' => 'bonus', 'operands' => ['lanterns' => 12, '#threshold' => 10], 'value' => 5]
```

The application turns the steps into the sentence it shows, in the player's language.

## Team scores

Each `TeamScore` holds the total of each member, in the team's order, the way they combine — sum or best — and the
result: everything needed to show `harbour-masters: 58 = u-101 33 + u-102 25`.
