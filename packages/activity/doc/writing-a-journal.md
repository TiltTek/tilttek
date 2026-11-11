# Writing a journal

An application that keeps its journal outside the bundle implements `Tilttek\Activity\Log\ActivityLog` on its
storage. The contract:

| Method | Must |
|---|---|
| `append($activity, $at)` | Write the activity once per idempotency key, even when two processes write it at the same moment; return the entry the storage kept and whether this call wrote it; raise `IdempotencyConflict` when the key holds another gesture (`Activity::sameGestureAs()`) |
| `cancel($key, $at)` | Write the cancellation once per key, carrying the recorded activity; return it and whether this call wrote it; return null when no activity is recorded under the key |
| `find($key)`, `isCancelled($key)` | Find the recorded entry of a key; say whether it is cancelled |
| `read($after, $limit, $filter)` | Return the matching entries after a position, in order, at most `limit`; check the bounds with `Reading::check()` |
| `lastPosition()` | Return the position of the last entry kept, 0 when there is none |
| `totals($filter)` | Count the matching recorded activities that are not cancelled, and sum their quantities |
| `eraseMember($member)` | Delete the member's entries, forget the member as the object of others', and say how many of each |

Positions only grow and are never given twice. The library's own test case, `ActivityLogTestCase` in its `tests/`,
states the contract in tests; copy it next to your implementation and run it against it.
