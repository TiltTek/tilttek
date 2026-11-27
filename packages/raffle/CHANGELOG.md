# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/raffle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency: `symfony/http-client-contracts` 3.8.

## 0.1.0-alpha - 2026-11-20

### Added

- `Raffles`: raffles opened with a stake, entries of opaque references, one chance per entrant and up to 100 000 of
  them, the entries sealed and hashed with the announcement published, the draw of 1 to 1 000 winners and 0 to 1 000
  alternates, the proof, and `Event\RaffleDrawn` dispatched with the raffle's identifier only.
- `Stake`, `SeedSource` and `RaffleStatus`: a raffle with a prize always seeded by a public beacon round, a raffle
  without a stake by the organiser's seed committed when it opens and revealed at the draw, or by the beacon on request.
- `Raffle`: the raffle as the store keeps it, at a version, written and read back as plain values field by field.
- `Announcement`: the number and the hash of the entrants, the commitment or the beacon round to come and the time it is
  published.
- `RaffleProof` and `RaffleVerifier`: the proof as JSON, read back within 16 MiB, the place of each name drawn — the
  podium, then the alternates —, and its check — the source the stake
  demands, the entrants listed once in byte order and hashed, the round published after the closing or the seed
  matching its commitment, the seed of the draw, and the names drawn again by the documented partial Fisher-Yates
  shuffle with rejection sampling —, with an optional signature verifier.
- `Beacon\Beacon`, `Beacon\BeaconChain`, `Beacon\BeaconVerification`, `Contract\RandomnessBeacon` and
  `Contract\BeaconSignatureVerifier`: beacon rounds whose randomness is the SHA-256 of their signature, chains and the
  times of their rounds, and the ports of a beacon and of a signature verifier.
- `Drand\DrandBeacon`, `Drand\DrandChains` and `Drand\DrandScheme`: drand read through Symfony HttpClient from 1 to 8
  HTTPS relays checked by `phil/url`, each relay bounded in time and size by the transport and the whole read on a
  PSR-20 clock, the chain's public key read and compared, a round kept when a quorum of relays agree on it, refused
  when one relay contradicts another; quicknet and the first network; the four signature schemes and the message a
  round signs; the BLS signature left to an optional verifier, a limit the documentation states.
- `Beacon\RelayReport`, `Beacon\RelayOutcome` and `Event\BeaconRelaysRead`: what each relay gave to each read —
  agreed, disagreed, invalid, a status, too large, timed out, unreachable or incomplete —, dispatched after every read
  and carried by the `BeaconUnavailable` or `BeaconRefused` that refuses it.
- `Contract\RaffleStore` and `Store\InMemoryRaffleStore`: raffles and entrants kept by version, an entry racing the
  closing refused as a conflict, entrants changed after the closing detected before a draw or a proof.
- `Exception\RaffleException`, with `InvalidRaffle`, `RaffleRefused`, `RaffleConflict`, `BeaconUnavailable` and
  `BeaconRefused`.
- Fuzz targets for a published proof and for the answers of a drand relay, and benchmarks of an entry, a draw among
  100 000 entrants and the check of a proof of 10 000 entrants.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
