# Writing a store

The library keeps nothing itself: four stores do, and an application implements them over its storage — or takes the
in-memory ones for tests. `tilttek/quests-bundle` implements them on Doctrine DBAL.

| Contract | Keeps | What matters |
|---|---|---|
| `ContributionStore` | What each activity brought to a step | Written once per quest, step and activity — `add()` returns false the second time; read by quest, members and period by moment, then activity, then step; `forget($since)` before a rebuild |
| `EnrolmentStore` | Acceptances and abandonments | One per quest and member, replaced on save; read by quest, ordered by member, and by member, ordered by quest |
| `CompletionStore` | The journal of accomplished quests | Written once per quest, occurrence and subject; `latest()` the subject's most recent; `forget($since)` returns what it forgot |
| `DuelStore` | Duels by key | Replaced on save; `accepted()` the accepted duels of a quest a member is a side of; `due()` the offers at or before a moment and the accepted duels ended by now; `reopen($since)` accepts again the duels won since |

Each store erases a member.

The tests of the library define a contract test case per store — `ContributionStoreTestCase`,
`EnrolmentStoreTestCase`, `CompletionStoreTestCase`, `DuelStoreTestCase` —: extend one with your store to check it
behaves as the library expects.
