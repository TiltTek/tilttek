# Tie-breakers

## Standings

The standings of a Swiss stage, of the pools or of one pool rank participants by points, then by the tie-breakers the
tournament declares, in order. Each tie-breaker splits the group still tied, and the next one is computed on each
smaller group left; a tie left at the end is broken by the draw of the published seed. Each `Standing` holds the rank,
the points, the score of each tie-breaker computed for the participant, by code, whether the draw had the last word —
`drawn` —, and whether the participant withdrew.

An elimination ranks by the round each participant reached, the champion first, then by seed.

## The tie-breakers of the library

| Code | Score |
|---|---|
| `buchholz` | The sum of the points of every opponent the participant was paired with, played or forfeited. A bye adds nothing. |
| `buchholz-cut-1`, `buchholz-cut-2` | The Buchholz without the lowest one or two scores of the opponents, so that one weak opponent drawn by chance weighs less. |
| `sonneborn-berger` | For each match, the opponent's points times the points the participant scored in it: a win against a strong opponent weighs most. |
| `head-to-head` | The points scored in the matches between the participants still tied, those alone; computed again on each smaller group. |
| `wins` | The matches won over the board; byes and forfeit wins are left out. |
| `mission-points` | The sum of the mission points scored, as miniatures games count them beside the win. |

With the points of the scoring in whole numbers, the Sonneborn-Berger score is the classic one times the points of a
win: the same order.

```php
new Tournament('cup', 'cup-2027', $participants, [Stage::swiss('swiss', 5)], tiebreakers: [
    'buchholz-cut-1',
    'buchholz',
    'sonneborn-berger',
    'head-to-head',
]);
```

A tournament declares up to eight tie-breakers, each once; an unknown code is refused when the tournament starts.

## The last draw

The draw orders the participants still tied by the SHA-256 of the seed and the participant, the smallest first. It
is the same each time, and anyone holding the published seed computes it: it never depends on a chance that cannot be
replayed. The same draw orders the participants before the first round when no seeding source gives their strengths.

## A tie-breaker of your own

Implement `Contract\Tiebreaker` — a stable code, and a score for each participant of a tied group, the highest first —
and give it to `Tiebreakers`:

```php
$tournaments = new Tournaments($store, new Tiebreakers(new GamesWonWithoutLoss()));
```

The group is given whole, with the `Records` of the stage, so that a tie-breaker that reads only the matches inside the
group is computed again on each smaller group. A code is 1 to 64 lowercase letters, digits or `-`, starting with a
letter, and is not the code of another tie-breaker.
