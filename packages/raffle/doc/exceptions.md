# Exceptions

Every exception of the library implements `Tilttek\Raffle\Exception\RaffleException`.

| Exception | Raised when | Message, for instance |
|---|---|---|
| `InvalidRaffle` | An identifier, an entrant, a number of winners, a margin, a beacon chain, a round, a relay or a quorum is refused, or a raffle or a proof does not read | `An entrant is an opaque reference of 1 to 128 letters, digits, dots, colons, underscores, tildes or hyphens.` |
| `RaffleRefused` | A raffle unknown or opened twice, a prize seeded by its organiser, no beacon, an entry once the entries are closed, twice or past the limit, a raffle sealed without entrants, drawn before it is sealed, twice or before its round, a proof before the draw | `member:1042 has entered the raffle door-prize already: an entrant holds one chance, never more.` |
| `RaffleConflict` | A change made from a version the store no longer holds, or entrants that no longer hash to the hash announced | `The raffle door-prize was changed from version 4, but the store holds version 5.` |
| `BeaconUnavailable` | Fewer relays answered than the quorum: the round is not published yet, or they are unreachable | `The beacon gave 1 answer(s) for round 1234567, 2 needed: the round is not published yet, or the relays are unreachable.` |
| `BeaconRefused` | A relay's answer does not check, relays disagree, a signature does not verify, or the beacon gave another round | `The relays give different signatures for round 1234567.` |

A `BeaconUnavailable` or `BeaconRefused` raised by a read of the drand relays carries its `Beacon\RelayReport` in
`$report`: what each relay gave. It is null otherwise.

`InvalidRaffle` extends `InvalidArgumentException`, `RaffleRefused` extends `DomainException`, and the others extend
`RuntimeException`.
