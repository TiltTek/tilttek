# Reference

| Type | Role |
|---|---|
| `FriendChallenges` | Creates challenges, moves them, settles their prizes and dispatches the events |
| `Challenge` | A challenge and its state machine; its record written and read back, checked |
| `ChallengeDraft` | What a member asks for: identifier, title, prize, end, invitees, visibility, validation, terms, votes needed |
| `ChallengeRules` | The site's bounds: shortest and longest challenge, review, largest prize |
| `ChallengeState` | `Open`, `Frozen`, `Won`, `Cancelled`, `Expired` or `Removed` |
| `Visibility` | `Friends` or `Open` |
| `Validation` | `Creator`, `Vote` or `Moderator` |
| `Proof` | A participant's proof: identifier, evidence, time, state, approvals and rejections |
| `ProofState` | `Pending`, `Accepted`, `Rejected` or `Removed` |
| `Prize` | An amount of a unit of points, never money |
| `PrizeHold` | The points held for a prize: key, payer, amount, unit, from when and until when |
| `Payout` | Points of a hold paid to a member |
| `Pseudonym` | What stands for an erased member: a reference of the kind `erased`, drawn for each challenge |
| `Report` | A member's report to the moderators: challenge, reporter, reason, time, proof, note |
| `ReportReason` | `Spam`, `Harassment`, `Cheating`, `Unsafe`, `Inappropriate` or `Other` |
| `Contract\ChallengeStore` | Where the challenges are kept |
| `Contract\PrizeEscrow` | Where the prizes are held: reserve, confirm, cancel |
| `Contract\Friendship` | Who is a friend of whom |
| `Contract\Moderation` | Where reports go, and who moderates |
| `Escrow\PointsPrizeEscrow` | The prizes held in the ledger of `tilttek/points` |
| `Escrow\InMemoryPrizeEscrow` | The prizes and the members' points in memory |
| `Store\InMemoryChallengeStore` | The challenges in memory, kept as records |
| `Store\InMemoryFriendship` | Friendships in memory |
| `Store\InMemoryModeration` | Moderators and reports in memory |
| `Event\ChallengeCreated`, `MemberInvited`, `ParticipantJoined`, `ProofSubmitted`, `ProofDecided`, `ChallengeWon`, `ChallengeEnded`, `ChallengeFrozen`, `ChallengeUnfrozen` | See [Extension points](extension-points.md#the-events) |

`Instant`, `Check`, `Reader` and `ChallengeRecord` are internal: the time in UTC, the checks of what the library
takes, and the reading and checking of a record.

## Exceptions

| Exception | Thrown when |
|---|---|
| `Exception\FriendChallengesException` | The interface of every exception of the library |
| `Exception\InvalidChallenge` | A value the library refuses: an identifier, a title, terms, a prize, a draft outside the rules, a time, a record, a reference of the kind `erased` given by a caller |
| `Exception\ChallengeRefused` | A move the rules do not allow: an unknown challenge or proof, a member who may not, a state or a time past its limit, a limit reached, an identifier reused |
| `Exception\ChallengeConflict` | Another writer kept the challenge first, three times in a row; a challenge of the same identifier kept already |
| `Exception\EscrowConflict` | An escrow move that contradicts its hold: another hold under its key, payouts over its amount, a hold settled twice |
| `Tilttek\Points\Exception\InsufficientPoints` | The creator's points do not cover the prize |

Nothing is kept and no point moves when one is thrown during a move, except an escrow failure after the move is kept,
which `settle()` makes good. Each message says what was expected, as
`member:eve is not invited to the challenge "01JD3", which is between friends.`
