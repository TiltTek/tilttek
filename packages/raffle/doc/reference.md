# Reference

| Type | Role |
|---|---|
| `Raffles` | Opens, takes entries, seals, draws and gives the announcement and the proof |
| `Raffle` | A raffle as the store keeps it, at a version; written and read back as plain values |
| `Stake` | `Prize` or `None`: what a raffle puts at stake, which decides its source |
| `SeedSource` | `Beacon` or `CommitReveal` |
| `RaffleStatus` | `Open`, `Sealed` or `Drawn` |
| `Announcement` | What is published when the entries close |
| `RaffleProof` | Everything a player needs to draw again, written and read as JSON; the place of each name drawn |
| `RaffleVerifier` | Lists the faults of a proof |
| `Beacon\Beacon` | One round of a beacon, its randomness checked against its signature |
| `Beacon\BeaconChain` | A chain of a beacon: hash, genesis, period, scheme and public key; the times of its rounds |
| `Beacon\BeaconVerification` | `Signature` or `RelayQuorum`: how a round was checked |
| `Beacon\RelayReport` | What every relay gave to one read, the quorum and whether the read was kept |
| `Beacon\RelayOutcome` | What one relay gave: `Agreed`, `Disagreed`, `Invalid`, `Status`, `TooLarge`, `TimedOut`, `Unreachable` or `Incomplete` |
| `Contract\RandomnessBeacon` | A public randomness beacon |
| `Contract\BeaconSignatureVerifier` | Verifies the signature of a round |
| `Contract\RaffleStore` | Where the raffles and their entrants are kept |
| `Drand\DrandBeacon` | drand read from several relays through Symfony HttpClient |
| `Drand\DrandChains` | The public chains of drand, and its relays |
| `Drand\DrandScheme` | The signature schemes of drand, and the message a round signs |
| `Event\RaffleDrawn` | Dispatched once a raffle is drawn, with its identifier |
| `Event\BeaconRelaysRead` | Dispatched after each read of the drand relays, with the chain's hash and the report |
| `Store\InMemoryRaffleStore` | The store in memory, for tests and scripts |
| `Exception\RaffleException` | The interface of every exception of the library |
| `Exception\InvalidRaffle`, `RaffleRefused`, `RaffleConflict`, `BeaconUnavailable`, `BeaconRefused` | See [Exceptions](exceptions.md) |

`Encoding`, `Entrants`, `Instant`, `Selection`, `SystemClock` and `Drand\DrandResponse` are internal.
