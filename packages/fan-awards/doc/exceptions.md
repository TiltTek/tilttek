# Exceptions

Every exception of the library implements `Exception\FanAwardsException`: catch this one to catch them all.

| Exception | Thrown when | Message |
|---|---|---|
| `AwardRefused` | The awards' rules refuse the act; `refusal` says why, `reason` holds the eligibility's reason | The refusal's message, below, then the reason |
| `UnknownAward` | No edition holds the key, or the edition no category of the key | `No edition is known as "…".`, `The edition "…" holds no category "…".` |
| `InvalidAward` | An edition, a category or a nominee out of bounds; a final method that is not ranked; a ballot that breaks its phase's rules | What is out of bounds; for a ballot, the rule of `phil/poll` it breaks |

`AwardRefused` extends `DomainException`, `UnknownAward` `OutOfBoundsException`, `InvalidAward`
`InvalidArgumentException`.

| `Refusal` | Message |
|---|---|
| `EditionExists` | An edition already holds this key. |
| `WrongPhase` | The edition is not in a phase that allows this act. |
| `VoterNotEligible` | This member may not vote in this edition. |
| `Ineligible` | This nominee is not eligible in this category. |
| `TooManyCandidates` | The category holds as many nominees as a ballot may offer. |
| `NotCounted` | The edition is not counted yet. |
| `NotAnnounced` | The results are published once the winners are announced. |
