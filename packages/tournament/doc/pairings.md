# Pairings

Every pairing of the library is computed from the results, the first order and the published seed alone: the same
tournament gives the same pairings each time, and anyone holding the seed can check them. Each table carries its
reasons, as `PairingReason` values of a `ReasonKind` an application translates, each with the participant it is about
and, when it has them, the other participant and the round it refers to.

## Swiss rounds

`Swiss\SwissPairer` pairs a round by score groups. It takes the FIDE Swiss rules as a written reference, not as a
certified implementation of them: a tournament that must follow the FIDE Dutch system to the letter, for a rated
event, uses a certified pairing program and publishes its round with `publishRound()`.

### What it holds to

- **No two meetings.** Two participants who played each other over the board are not paired again while another
  pairing of the round avoids it. A forfeit is not a meeting.
- **One bye.** With an odd number of participants, the bye goes to the lowest ranked participant who never had one,
  and to a participant with fewer points before one with more. A second bye is given only when everyone left had one.
- **Score groups.** Participants are paired with others of their points; when a group cannot be paired whole, a
  participant floats to the nearest group, and one large gap weighs more than two small ones.
- **Higher half against lower half.** Inside a score group, the first of the higher half meets the first of the lower
  half, and so on, as far as the other rules allow.
- **Sides alternated.** A participant who played second more often, or last, plays first. When two meet again, each
  plays the side they did not play the time before. Between two participants due to the same side, the one more due
  gets it; with the same history, the higher ranked gets the first side on odd tables.

The bye, its points and its reason are on the round prepared, before it is published, so that an organiser shows why
the round has a bye and who has it.

### How it works

The participants are ranked by points, then by the first order. Each possible pair has a cost, and the round is the
set of pairs of the lowest total cost. From the heaviest to the lightest, the costs weigh:

1. a rematch or a second bye — above every other cost of a round put together;
2. the bye given to a participant with more points;
3. the difference of points between the two of a pair, squared;
4. two participants due to play the same side, more so when both must;
5. inside a score group, a pair other than higher half against lower half; between two groups, a pair far apart in
   the ranking; for the bye, a participant ranked higher.

Up to 16 participants — the bye counted as one — the lowest total cost is found exactly, by a dynamic programme over the
sets of participants left; its time grows as 2ⁿ, which is why it stops there. `new SwissPairer(exactLimit: 20)` raises
the limit to 20 at most. Above, a bounded search pairs each participant in turn with the cheapest one left, then
exchanges the partners of two pairs while an exchange lowers the cost, for at most 20 passes —
`new SwissPairer(passes: 100)` allows more —, each pass reading every two pairs once.

| Participants | Pairing | Time of a round, measured |
|---|---|---|
| 16 | exact | about 2 ms |
| 20, with `exactLimit: 20` | exact | about 20 ms |
| 64 | bounded | about 2 ms |
| 512 | bounded | about 90 ms |

### Limits

- **One round at a time.** Each round is paired at its lowest cost without looking ahead: a pairing can leave a later
  round with no pairing free of rematches, when a stage has nearly as many rounds as participants — six participants
  over five rounds, for instance. The pairer then pairs the rematch of the lowest cost and says so with `Rematch`
  reasons. A field of at least twice as many participants as rounds never met this case in the property tests.
- **Bounded search above the exact limit.** The exchanges of two pairs miss a better pairing that needs three pairs or
  more to change at once. The result is still the same each time; in the property tests, fields up to 64 participants
  with up to 7 rounds kept every rule above.
- **Simplified sides.** The side rules are those listed above, not every colour criterion of the FIDE rules; there is no
  float history beyond the gap of points.
- **512 participants a round at most.**

### Reasons

| Reason | Given when |
|---|---|
| `Bye` | The participant has the bye: the lowest ranked who never had one. |
| `ByeRepeated` | The participant has a second bye: everyone left had one. |
| `FirstPlayerAlternated` | The participant plays first: they played second more often, or last. |
| `FirstPlayerRepeated` | The participant plays first again: both were due to play first, the other less so. |
| `FirstPlayerAlternatedRematch` | The participant plays first: they played second when the two met before. |
| `FirstPlayerByRank` | The same history of sides: the higher ranked plays first on odd tables. |
| `RematchAvoided` | The participant is not paired with another of the same points they met already, in the round given. |
| `Rematch` | The participant meets an opponent again: no pairing of the round avoided it. |
| `Floated` | The participant meets an opponent of other points: their score group could not be paired whole. |

## Pools

`RoundRobin\BergerTable` builds the rounds of a pool by the Berger tables, with the circle method: the last number
stays in place and the others turn by one each round. Each pair meets once a leg, every round has the same number of
tables, and each participant plays first in half of their matches, give or take one. A pool of an odd size has a rest
in each round, each participant in turn; the rest is a table of one, with `Rest`, which scores nothing and shows in the
participant's schedule. A second leg plays the rounds of the first again with the sides swapped, with `ReturnMatch`.

`RoundRobin\Pools` spreads the participants over the pools in a snake, by the first order: the first seeds head pools A,
B, C in order, the next ones fill them back from the last, so that the pools are as even as the seeds say. The rounds of
the pools are played side by side: round n holds the tables of round n of every pool, numbered on from one pool to the
next, each table with its pool. Their reason is `Scheduled`.

## Brackets

`Elimination\Bracket` places the seeds so that the two first meet in the final at the earliest, the four first in the
semi-finals, and each seed meets the weakest left in the first round: 1 against 8, 4 against 5, 2 against 7, 3
against 6 in a bracket of eight. A bracket's size is the next power of two; the missing seeds are byes, so the first
seeds go through the first round without playing, shown as tables of one. Each later match takes the winners of two
matches before it. The tables are numbered by match, and their reason is `Seeded`.

A bracket made for a second stage is prepared before its qualifiers are known: its places are `Elimination\Slot`
values that read `swiss#1`, `pools/A#1`, `pools/B#2` — the rank of a stage or of a pool — until the stage before is over,
then hold the participants. `Tournaments::bracket()` gives it at any time, filled with what is known.

A match has a winner: a draw is refused, and is decided by the game's own rules before it is entered. A participant
who withdraws before playing a match loses it by walkover; a match both sides lost leaves the next place empty, and
the participant there goes through.
