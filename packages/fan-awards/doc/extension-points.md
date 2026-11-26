# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Default |
|---|---|---|
| `Contract\AwardStore` | Where editions, nominees, ballots and counts are kept | `Store\InMemoryAwardStore` in tests |
| `Contract\NomineeEligibility` | Whether a nominee may stand in a category, and why not | `Eligibility\AnyNominee` |
| `Contract\VoterEligibility` | Whether a member may vote in an edition | `Eligibility\AnyVoter` |
| `Phil\Poll\CountingMethods` | The counting methods, a ranked one of its own among them | `CountingMethods::defaults()` |
| `Psr\Clock\ClockInterface` | The moment each phase begins | — |

## Writing a store

An `AwardStore` keeps:

- the editions by key — `edition()` and `saveEdition()`, which replaces the edition of the same key;
- the nominees of each category and phase, in the order they were added — `candidates()` and `addCandidate()`;
- one ballot per voter, category and phase — `ballot()` and `saveBallot()`, which replaces the earlier one — and every
  ballot of a category and phase, those of erased voters included, read once by the count through `ballots()`, which
  may stream them from a table;
- the count of each phase — `saveCount()` and `counts()`, in the order of the phases;
- `forget($voter)`, which detaches the voter from their ballots and keeps the ballots.

Voters are named by their references written `kind:identifier`; a ballot is the list of its nominees' keys, in the
voter's order.

## Eligibility

`NomineeEligibility::refusal()` returns null for an eligible nominee, or the reason it is not, which reaches the voter.
`VoterEligibility::mayVote()` answers from the server's own records. Both keep no state between two calls. Neither may
tie a vote to a payment nor a purchase.
