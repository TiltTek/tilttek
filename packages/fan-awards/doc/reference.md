# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `FanAwards` | Creates editions, takes nominees and ballots, advances and counts phases, reads results, erases voters |
| `Edition` | An edition: key, name, categories, semifinal or not, phase and since when |
| `Category` | A category and its rules: nominations, semifinalists, finalists, final method, "no award" |
| `Nominee` | A nominee: key, title, reference; `NO_AWARD`, the reserved key of "no award" |
| `Phase` | `Draft`, `Nomination`, `Semifinal`, `Final`, `Counted`, `Announced`, and how they follow |
| `PhaseCount` | The count of one phase: its nominees, the `phil/poll` result, the nominees it advanced |
| `Results` | A category's winners, "no award", and the count of each phase |
| `Refusal` | Why an act is refused |
| `Contract\AwardStore` | Where editions, nominees, ballots and counts are kept |
| `Contract\NomineeEligibility` | Whether a nominee may stand in a category |
| `Contract\VoterEligibility` | Whether a member may vote in an edition |
| `Eligibility\AnyNominee` | Every nominee eligible |
| `Eligibility\AnyVoter` | Every member a voter |
| `Store\InMemoryAwardStore` | The store in memory |
| `Exception\FanAwardsException` | Every exception of the library |
| `Exception\AwardRefused` | An act refused, with its `Refusal` and the eligibility's reason |
| `Exception\UnknownAward` | No edition under a key, or no category under a key |
| `Exception\InvalidAward` | A value out of bounds, or a ballot that breaks its phase's rules |
