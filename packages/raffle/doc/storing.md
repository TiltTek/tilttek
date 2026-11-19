# Storing

`Contract\RaffleStore` keeps the raffles and their entrants:

| Method | Does |
|---|---|
| `find($raffle)` | The raffle, or null |
| `add($raffle)` | Keeps a newly opened raffle, at version 1; `RaffleConflict` when the identifier is taken |
| `save($raffle)` | Keeps the raffle's next version |
| `enter($raffle, $entrant)` | Adds the entrant and keeps the raffle's next version, both or neither; false, and nothing kept, when the entrant is in already |
| `entrants($raffle)` | The entrants, in any order |

Every change stores the raffle's next version and is refused with `RaffleConflict` when the version stored is not the
one it was made from: an entry that comes while the entries close never slips past the hash of the entrants, since the
closing and the entry cannot both land. The caller reads the raffle again and retries. On a database, the raffle row
carries its version, updated with `WHERE version = ?`, and the entrants have a unique key on the raffle and the entrant,
written in the same transaction.

`Raffle::toArray()` and `Raffle::fromArray()` give the plain values to keep, read back field by field. A raffle seeded
by commit-reveal holds its seed: whoever reads it knows the winners as soon as the entries close. Keep it apart from
what the public reads, encrypted at rest when the storage is shared.

`Raffles` checks, when it draws and when it gives a proof, that the entrants kept still hash to the hash announced, and
throws `RaffleConflict` otherwise. `Store\InMemoryRaffleStore` keeps everything in the process, for tests and scripts.
