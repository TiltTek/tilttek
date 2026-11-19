# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Where |
|---|---|---|
| `Contract\RaffleStore` | The storage of the raffles and their entrants | [Storing](storing.md) |
| `Contract\RandomnessBeacon` | A public randomness beacon; `Drand\DrandBeacon` reads drand | [drand](drand.md) |
| `Contract\BeaconSignatureVerifier` | The verification of a round's signature, a BLS one for drand | [drand](drand.md#verifying-a-signature) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Who hears of each draw, through `Event\RaffleDrawn`, and of each read of the relays, through `Event\BeaconRelaysRead` | [Running a raffle](running-a-raffle.md#the-event), [drand](drand.md#what-each-relay-gave) |
| `Psr\Clock\ClockInterface` | The time of the openings, closings and draws, and the end of each read of the relays | [Installation](installation.md#wiring-the-library) |
| `Random\Randomizer` | The source of the organisers' seeds | [Installation](installation.md#wiring-the-library) |
| `Symfony\Contracts\HttpClient\HttpClientInterface` | The client the relays are read with | [drand](drand.md#relays-and-the-quorum) |

Every point is passed to a constructor; none of the library's services keeps state between two calls.
