# tilttek/raffle

**Verifiable raffles without buying chances: a future drand round seeds a prize draw, commit-reveal a draw without a
stake, and a proof any player checks.**

A raffle draws winners among entrants, and every entrant holds one chance: the model counts entrants, never tickets,
and nothing in it buys, weighs or multiplies a chance. When the entries close, the entrants are listed in byte order
and hashed, and the organiser announces that hash with the source of the draw, before anyone can know the winners.

For a raffle with a prize, the randomness comes from a public randomness beacon: the first round of drand published
after the entries close and a margin, a round nobody — the organiser included — knows in advance nor can choose. For a
raffle without a stake, the organiser commits to a secret seed when the raffle opens and reveals it at the draw; no
network is needed. Either way, the seed of the draw is the hash of the raffle, its entrants and that randomness, and
the winners, then the alternates who replace a winner who does not claim the prize, come from a partial Fisher-Yates
shuffle written so that any language draws them again. The proof holds everything a player needs to check the draw
on their own.

It is for an application that holds giveaways, door prizes or random picks at a convention, a game night or a stream,
and wants them fair and seen to be fair. It is a plain PHP library: the drand adapter reads relays through Symfony
HttpClient, their addresses checked by `phil/url`; the store is the application's; the draw dispatches an event the
application listens to, to announce the winners or play a celebration.

## Status

| Part | State |
|---|---|
| `Raffles`: opening, entries, sealing with the announcement, the draw and the proof | Implemented and tested |
| `Raffle`, `Stake`, `SeedSource`, `RaffleStatus`: the raffle as stored, one chance per entrant | Implemented and tested |
| `Announcement`, `RaffleProof`: what is published when the entries close and after the draw | Implemented and tested |
| `RaffleVerifier`: a proof checked on the player's side, with an optional signature verifier | Implemented and tested |
| `Beacon\Beacon`, `Beacon\BeaconChain`, `Beacon\BeaconVerification`, `Contract\RandomnessBeacon`, `Contract\BeaconSignatureVerifier` | Implemented and tested |
| `Drand\DrandBeacon`, `Drand\DrandChains`, `Drand\DrandScheme`: drand read from several relays, the rounds confirmed by a quorum | Implemented and tested |
| `Beacon\RelayReport`, `Beacon\RelayOutcome`, `Event\BeaconRelaysRead`: each relay's failures and disagreements reported | Implemented and tested |
| BLS verification of a drand signature | Not provided: an application plugs a `BeaconSignatureVerifier`; see [the known limit](doc/drand.md#the-known-limit) |
| `Contract\RaffleStore`, `Store\InMemoryRaffleStore`, `Event\RaffleDrawn` | Implemented and tested |
| Exceptions under one `RaffleException` interface | Implemented and tested |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Running a raffle](doc/running-a-raffle.md) | Stakes and sources, entries, sealing, the draw, alternates and the event. |
| [The draw and its proof](doc/draw-and-proof.md) | The hashes, the shuffle, the proof and how a player checks it alone. |
| [drand](doc/drand.md) | The beacon adapter: chains, relays, the quorum, and verifying a signature. |
| [Storing](doc/storing.md) | The store contract, versions and concurrent entries. |
| [Extension points](doc/extension-points.md) | What an application gives the library. |
| [Exceptions](doc/exceptions.md) | `RaffleException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, fuzz targets and benchmarks. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Symfony\Component\HttpClient\HttpClient;
use Tilttek\Raffle\Drand\{DrandBeacon, DrandChains};
use Tilttek\Raffle\{Raffles, Stake};
use Tilttek\Raffle\Store\InMemoryRaffleStore;

$beacon = new DrandBeacon(HttpClient::create(), DrandChains::quicknet(), DrandChains::RELAYS);
$raffles = new Raffles(new InMemoryRaffleStore(), $beacon);

$raffles->open('door-prize', Stake::Prize, winners: 3, alternates: 2);
$raffles->enter('door-prize', 'member:1042');           // once per entrant
$announcement = $raffles->seal('door-prize');           // publish it: the hash, the round, its time
// ... once $announcement->drawableAt() has passed:
$proof = $raffles->draw('door-prize');
$proof->winnerList();                                  // the podium, in order: $proof->place($entrant)
$json = $proof->toJson();                               // what a player checks
```

## Requirements

PHP 8.5 or later with `ext-sodium`; `phil/url` 0.1, `psr/clock`, `psr/event-dispatcher` and
`symfony/http-client-contracts`; an HTTP client, such as `symfony/http-client`, for the drand adapter.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
