# drand

drand is the distributed randomness beacon of the League of Entropy: a network of independent operators that
publishes, at fixed times, a round whose signature none of them can compute alone, and whose randomness is the SHA-256
of that signature. `Drand\DrandBeacon` reads it over HTTP.

## Chains

| Chain | `DrandChains::` | Period | Genesis | Scheme |
|---|---|---|---|---|
| quicknet | `quicknet()` | 3 s | 2023-08-23 | `bls-unchained-g1-rfc9380`: signatures on G1, each round signed alone |
| The first network | `mainnet()` | 30 s | 2020-07-22 | `pedersen-bls-chained`: signatures on G2, each round signing the one before |

Round `r` of a chain is published at `genesis + (r − 1) × period`. Any other chain is a `Beacon\BeaconChain` built
with its hash, genesis, period and scheme. The public key is not written in the library: `chain()` reads it from the
relays, checks that every relay describes the chain expected — the same hash, genesis, period and scheme — and that
they give the same key.

## Relays and the quorum

```php
$beacon = new DrandBeacon(
    $httpClient,
    DrandChains::quicknet(),
    DrandChains::RELAYS,     // api.drand.sh, api2.drand.sh, api3.drand.sh, drand.cloudflare.com
    signatures: null,        // a BeaconSignatureVerifier, or null
    quorum: null,            // 2 without a verifier, 1 with one
    timeout: 5.0,            // per relay by the transport, and for the whole read on the clock
    events: $dispatcher,     // told of each read through Event\BeaconRelaysRead; null to dispatch nothing
    clock: $clock,           // a PSR-20 clock; the system's by default
);
```

The relays are 1 to 8 HTTPS addresses without a query nor a fragment, read by `phil/url`. A read asks every relay at
once — `GET {relay}/{chain hash}/info` or `GET {relay}/{chain hash}/public/{round}` —, follows no redirect, and keeps
an answer only when it comes whole, with the status `200`, within the timeout and 4 096 bytes. The timeout bounds each
relay through the transport's `timeout` and `max_duration`, and the whole read on the clock: once it has passed, the
relays still answering are cancelled. A round is kept when:

- its number is the round asked for, its signature has the length of the scheme — and, for a chained scheme, so has
  the signature of the round before —, and its randomness is the SHA-256 of its signature;
- every relay that answered gives the same signature;
- at least the quorum of relays answered.

Fewer answers than the quorum throw `BeaconUnavailable`: the round is not published yet, or the relays are
unreachable, and the draw is tried again. An answer that does not check, or two relays that disagree, throw
`BeaconRefused`: nothing is drawn from a beacon told two ways. Agreeing on the signature is agreeing on the randomness,
its SHA-256.

## What each relay gave

Every read — of a round or of the chain information — gives a `Beacon\RelayReport`: the outcome of each relay, by its
address in the order configured, the quorum and whether the read was kept. It is dispatched through
`Event\BeaconRelaysRead`, with the chain's hash, after every read, kept or not, and it is the `$report` of the
`BeaconUnavailable` or `BeaconRefused` that refuses the read.

| `Beacon\RelayOutcome` | The relay |
|---|---|
| `Agreed` | gave an answer that checks, with the signature most relays gave |
| `Disagreed` | gave an answer that checks, with another signature; on a tie, the first relay's is the one compared to |
| `Invalid` | gave a whole answer that does not check |
| `Status` | answered with a status other than `200` |
| `TooLarge` | gave more than 4 096 bytes |
| `TimedOut` | stayed silent past the timeout, or was still answering when the read ran out of time |
| `Unreachable` | could not be reached, or its answer broke |
| `Incomplete` | ended its answer before it was whole |

A relay that fails without contradicting the others is reported, not held against the read: the quorum is there to bear
it. A listener that counts the faults of each relay sees one going wrong before the quorum is lost:

```php
$dispatcher->addListener(BeaconRelaysRead::class, function (BeaconRelaysRead $read) use ($metrics): void {
    foreach ($read->report->faults() as $relay => $outcome) {
        $metrics->increment('drand.relay.fault', ['relay' => $relay, 'outcome' => $outcome->value]);
    }
});
```

## Verifying a signature

A drand signature is a BLS signature on the curve BLS12-381. The library does not verify it itself: PHP has no
pairing on that curve, and the library takes no extension nor library for it. An application that has one — a binding
to a BLS library through FFI, a verification service — implements `Contract\BeaconSignatureVerifier` and passes it to
`DrandBeacon` and to `RaffleVerifier`. `DrandScheme::message($round, $previousSignature)` gives the 32 bytes a round
signs: the SHA-256 of the round as 8 big-endian bytes, preceded, for a chained scheme, by the bytes of the previous
signature. The domain separation tag of quicknet is `BLS_SIG_BLS12381G1_XMD:SHA-256_SSWU_RO_NUL_`.

Without a verifier, the round is confirmed by at least two relays run by different operators, and the proof says
`"verification": "relay-quorum"`: anyone verifies the signature afterwards with drand's own clients, from the round,
the signature and the chain in the proof. With a verifier, it says `"signature"`.

## The known limit

Without a `BeaconSignatureVerifier`, the library does not verify the BLS signature of a round: it trusts that the
relays configured, run by different operators, do not all lie the same way. Relays that agreed on a forged round would
pass the quorum. The proof keeps everything needed to catch it afterwards — the round, its signature and the chain's
public key —, and a player who verifies the signature with drand's tools sees the forgery; the draw cannot be checked
as honest until someone does. To close the gap, configure relays of distinct operators, set the quorum to all of them
for a raffle of value, and plug a verifier.

## Behind a proxy or a firewall

The relays are configured by the application, never given by a visitor. On a server that must not reach its own
network, wrap the client in Symfony's `NoPrivateNetworkHttpClient`.
