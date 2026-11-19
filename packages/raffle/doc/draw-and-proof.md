# The draw and its proof

## The hashes

Every hash is the SHA-256, in 64 lower-case hexadecimal digits, of a canonical JSON: PHP's `json_encode()` with
`JSON_UNESCAPED_SLASHES` and `JSON_UNESCAPED_UNICODE`, no space.

| Value | Hash of |
|---|---|
| The entrants hash, announced when the entries close | `["tilttek-raffle/1", raffle, [entrants in byte order]]` |
| The commitment of a commit-reveal raffle, published when it opens | the 64 hexadecimal digits of the seed |
| The seed of the draw | `["tilttek-raffle/1", raffle, entrants hash, randomness]` |

The randomness is the beacon round's — the SHA-256 of its signature — or the seed the organiser revealed. Byte order
is the order of PHP's `sort($entrants, SORT_STRING)`: `B` before `a`, `10` before `9`.

## The shuffle

The names drawn are the first `min(winners + alternates, entrants)` of a partial Fisher-Yates shuffle of the entrants
in byte order. Step `i`, from 0, swaps entrant `i` with entrant `i + u`, where `u` is drawn uniformly below the number
of entrants left, `n - i`:

1. compute the SHA-256 of the 32 bytes of the seed followed by `i` and an attempt counter, from 0, both as 4-byte
   big-endian numbers;
2. read its first 6 bytes as a big-endian whole number `v`, below 2⁴⁸;
3. if `v` is below the largest multiple of `n - i` that is at most 2⁴⁸, `u` is `v` modulo `n - i`; otherwise add one
   to the attempt counter and start again.

Rejecting the values past the last whole multiple keeps every entrant equally likely; with 100 000 entrants a value is
rejected less than once in a billion draws. The names drawn are the winners first, then the alternates.

A raffle of several prizes — a podium — is one draw: the first name drawn takes the first prize, the second the second,
and so on, then the alternates in the order they replace a winner. `RaffleProof::place($entrant)` gives that place, 1
for the first prize, or null for an entrant not drawn. The order is part of the proof: a player who swaps two places or
drops a name breaks it.

## The proof

`RaffleProof::toJson()` writes:

```json
{
  "version": "tilttek-raffle/1",
  "raffle": "door-prize",
  "stake": "prize",
  "winners": 3,
  "alternates": 2,
  "entrants": ["member:1042", "member:1077"],
  "entrants_hash": "…",
  "source": {
    "kind": "beacon",
    "chain": {"hash": "52db9b…", "genesis": 1692803367, "period": 3, "scheme": "bls-unchained-g1-rfc9380", "public_key": "…"},
    "beacon": {"round": 1234567, "randomness": "…", "signature": "…", "previous_signature": null, "verification": "relay-quorum"}
  },
  "seed": "…",
  "drawn": ["member:1077", "member:1042"],
  "sealed_at": "2026-11-12T20:00:00.000000Z",
  "drawn_at": "2026-11-12T20:01:00.000000Z"
}
```

A commit-reveal proof has `"source": {"kind": "commit-reveal", "commitment": "…", "seed": "…"}`. `fromJson()` reads at
most 16 MiB, 8 levels deep, every field checked, and throws `InvalidRaffle` for anything else.

## Checking a proof

`RaffleVerifier::problems($proof)` lists what breaks the proof, one sentence per fault; `holds()` is true when there is
none; `problemsOfJson($json)` reads the published text first, a proof that does not read being one fault. It checks:

- a raffle with a prize drew from a beacon round;
- the entrants are each listed once, in byte order, and hash to the entrants hash;
- the beacon round is the first published after the closing or a later one, or the seed revealed matches the
  commitment;
- the seed of the draw is the hash of the raffle, the entrants hash and the randomness;
- the shuffle from that seed gives the names drawn, in the same order.

Given a `BeaconSignatureVerifier`, it also verifies the round's signature against the chain's public key. Without
one, a player verifies the signature with drand's own clients: the round, its signature and the chain are in the proof.
A player also checks that the entrants hash and the round are the ones the organiser announced before the draw, and
that their own reference is among the entrants.
