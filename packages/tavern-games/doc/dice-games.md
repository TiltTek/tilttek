# The dice games

## Pig

`Game\Pig`, named `pig`, for 2 to 8 seats: in their turn, a seat rolls one die as often as they like, adding each face
to the turn's total, and holds to bank it; a 1 loses the turn's total and ends the turn. The first seat to bank the
target wins.

| Part | What it is |
|---|---|
| Option | `target`, the points to bank: 100, from 20 to 200 |
| Moves | `pig.roll` and `pig.hold`; the timeout of a turn holds |
| Outcome | The seat that banks the target wins, `target-reached`; the banked points of each seat as `score` |
| View | Everything, Pig has no secret: the seat playing, the banked points, the turn's total, the last face, the target |

## Ship, Captain and Crew

`Game\ShipCaptainCrew`, named `ship-captain-crew`, for 2 to 8 seats: each seat in turn rolls five dice, up to three
times, and must set aside a 6 — the ship —, then a 5 — the captain —, then a 4 — the crew —, each only once the one
before it is set aside. With all three, the two other dice are the cargo, which the seat may roll again while rolls
remain. Once every seat has played, the most cargo wins.

| Part | What it is |
|---|---|
| Moves | `ship-captain-crew.roll`; `ship-captain-crew.stop`, which keeps the cargo once the crew is aboard; the timeout of a turn stops |
| Outcome | The most cargo wins, `most-cargo`; equal cargoes share the win, `tie`; when nobody carries any, the game ends with no winner, `no-cargo`; the cargo of each seat as `cargo` |
| View | Everything: the seat playing, its rolls, how many of ship, captain and crew are aboard, the dice rolled last, its cargo once the crew is aboard, and the cargo of each seat that has played |

The ship, captain and crew are set aside as soon as a roll shows them, in their order: `ShipCaptainCrew::setAside()`.
A turn that ends without the crew carries nothing. `ShipCaptainCrew::chase()` gives the odds of the seat playing
([Odds](odds.md#the-chase-of-a-cargo)).
