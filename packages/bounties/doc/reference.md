# Reference

| Type | Role |
|---|---|
| `Bounties` | Offers bounties, moves them, settles their points and dispatches the events |
| `Bounty` | A bounty and its state machine; its awards, payouts and record written and read back, checked |
| `BountyDraft` | What a member asks for: identifier, subject, reason, amount, end |
| `BountyRules` | The site's bounds: shortest and longest bounty, grace, smallest and largest amount, open bounties a sponsor |
| `BountyState` | `Open`, `Awarded`, `Expired` or `Cancelled` |
| `BountyReason` | `Attention`, `Detail`, `Sources`, `Reward`, `Creativity` or `Other` |
| `Claim` | A member's claim: identifier, claimant, evidence, time |
| `Award` | Points the sponsor awards to a claim: identifier, claim, amount, time |
| `Prize` | An amount of a unit of points, never money |
| `BountyHold` | The points held for a bounty: key, payer, amount, unit, from when and until when |
| `Payout` | Points of a hold paid to a member |
| `Pseudonym` | What stands for an erased member: a reference of the kind `erased`, drawn for each bounty |
| `Contract\BountyStore` | Where the bounties are kept, one open bounty a subject |
| `Contract\BountyEscrow` | Where the points are held: reserve, confirm, cancel |
| `Escrow\PointsBountyEscrow` | The points held in the ledger of `tilttek/points` |
| `Escrow\InMemoryBountyEscrow` | The points and the holds in memory |
| `Store\InMemoryBountyStore` | The bounties in memory, kept as records |
| `Event\BountyOffered`, `BountyClaimed`, `AwardGiven`, `BountyAwarded`, `BountyClosed` | See [Extension points](extension-points.md#the-events) |

`Instant`, `Check`, `Reader` and `BountyRecord` are internal: the time in UTC, the checks of what the library takes,
and the reading and checking of a record.

## Exceptions

| Exception | Thrown when |
|---|---|
| `Exception\BountiesException` | The interface of every exception of the library |
| `Exception\InvalidBounty` | A value the library refuses: an identifier, an amount, a unit, a draft outside the rules, a time, a record, a reference of the kind `erased` given by a caller |
| `Exception\BountyRefused` | A move the rules do not allow: an unknown bounty or claim, a member who may not, a state or a time past its limit, a limit reached, an amount over what is left, a subject taken, an identifier reused |
| `Exception\BountyConflict` | Another writer kept the bounty first, three times in a row; a bounty of the same identifier, or on the same subject, kept already |
| `Exception\EscrowConflict` | An escrow move that contradicts its hold: another hold under its key, payouts over its amount, a hold settled twice |
| `Tilttek\Points\Exception\InsufficientPoints` | The sponsor's points do not cover the bounty |

Nothing is kept and no point moves when one is thrown during a move, except an escrow failure after the move is kept,
which `settle()` makes good. Each message says what was expected, as
`The bounty "01JD4" has 40 points left to award; 41 is too many.`
