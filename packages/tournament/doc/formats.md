# Tournaments and stages

## A tournament

A `Tournament` is declared once, when it starts:

| Field | What it holds |
|---|---|
| `id` | The identifier of the tournament. |
| `seed` | A published seed, 1 to 128 printable ASCII characters without spaces. It sets the first order of participants without a seeding, and breaks the ties left at the end of the standings. Anyone holding the seed and the results computes the same pairings and standings again. |
| `participants` | 2 to 512 identifiers, each once. |
| `stages` | 1 to 4 stages, in order. |
| `scoring` | The points of a match; `Scoring` gives 2 for a win, 1 for a draw, 0 for a loss. |
| `tiebreakers` | Up to 8 codes of tie-breakers, in the order they apply. |

Every identifier — of a tournament, a stage, a participant, or of who enters a result — is 1 to 128 letters, digits,
`_`, `.`, `:`, `@` or `-`, starting with a letter. It is stable and opaque: an application keeps the names, the library
never sees them, and no pairing, standing or event carries what identifies a person.

## Stages

| Stage | Made by | Pairs |
|---|---|---|
| Swiss | `Stage::swiss($id, $rounds, $qualifiers)` | 1 to 64 rounds, each paired by score groups; the first `$qualifiers` of the standings go on. |
| Round robin | `Stage::roundRobin($id, $pools, $legs, $qualifiers)` | 1 to 26 pools, filled in a snake by seed, everyone meeting everyone of their pool once a leg, in one or two legs; the first `$qualifiers` of each pool go on. |
| Single elimination | `Stage::singleElimination($id)` | A bracket by seeds; a match lost is the end. It is the last stage. |

A stage starts when the one before it is over. Its participants are the qualifiers of the one before, by rank, the
pools taken in turn at each rank: the first of pool A, the first of pool B, then the second of pool A, and so on. A
participant who withdrew does not qualify; the next one does. Two-stage tournaments — pools or Swiss rounds, then a
seeded bracket — are declared as two stages.

## Scoring

`Scoring` holds whole numbers, from 0 to 1 000: a win, a draw, a loss, and, when they differ from a win and a loss, a
bye, a forfeit win and a forfeit loss. Chess counts 1, ½ and 0; the default 2, 1 and 0 keeps the same order in whole
numbers. `Scoring::threeForAWin()` gives 3, 1 and 0. A draw gives no more than a win, and no less than a loss.

## Running a tournament

`Tournaments` is the service an application calls; it keeps nothing between calls, and reads and saves the tournament
through a `Contract\TournamentStore`.

```php
$tournaments->start($tournament);                 // the first order is fixed now
$round = $tournaments->prepareRound('cup');       // the next round, with its reasons, not published
$tournaments->publishRound('cup', $round);        // or a Round seating the same participants otherwise
$tournaments->reschedule('cup', 'swiss', 1, new DateTimeImmutable('2027-04-03 13:00'));
$tournaments->record('cup', $round->key(1), Result::draw(), 'm-104');
$tournaments->standings('cup');                   // the stage under way, or a stage and a pool given
$tournaments->bracket('cup');                     // the elimination, filled with what is known
$tournaments->schedule('cup', 'm-104');           // every round of a participant, rests included
$tournaments->explain('cup', 'swiss', 2, 'm-104', 'm-221');
```

The next round is prepared only when every table of the round before has a result and no conflict is waiting. A
published round seats each participant of the stage still in, once; an organiser may seat them otherwise, and the
library keeps that round as published. Each table has a key, `stage#round#table`, the one results are entered under.

A round has a number, which orders it, and a date it starts, which may be set, moved or cleared after it is published
without changing its number or its tables.

### First order

The participants are ordered once, when the tournament starts: by the strengths a `Contract\SeedingSource` gives, the
strongest first, then those of the same strength or without one in the order of the published seed. Without a seeding
source, the seed orders them all. This order ranks the participants before the first round of a Swiss stage, numbers
them in the Berger tables, and seeds the bracket.

### Schedule

`schedule()` gives a participant every round published, then, for pools, the rounds the Berger table sets already:
the opponent, the side, the pool, the date when set, the rest of a round where the pool is odd, and whether the round is
published or only foreseen.

### Explanations

`explain()` tells why two participants meet in a published round, or why they do not, as an `ExplanationKind` an
application translates: they meet; they met already, in the round given; one of them has the bye or rests; one of them
is absent — withdrawn, or not in the stage; they have different points; they are in different pools; the schedule of
the pool sets their meeting in another round; or, with the same points and never met, another pairing of their group
cost less.
