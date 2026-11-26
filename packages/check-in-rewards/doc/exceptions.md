# Exceptions

Every exception of the library implements `Exception\CheckInRewardsException`: catch this one to catch them all.

| Exception | Thrown when | Message, for instance |
|---|---|---|
| `InvalidPresence` | A `Presence` whose event reference, place or track does not fit in the context of an activity: 1 to 100 bytes | `The place of a presence is 1 to 100 bytes; this one is 0.` |
| `InvalidCriterion` | An `AttendanceCriterion` counting fewer than 1 or more than 1,000,000,000, or naming a place or an event reference that does not fit in 1 to 100 bytes | `An attendance criterion counts 1 to 1000000000; this one counts 0.` |
| `AccessDenied` | `AttendanceReader` asked by a viewer for the attendance of another member | `"member-12" cannot read the attendance of "member-7": a member's attendance is theirs alone.` |

`InvalidPresence` and `InvalidCriterion` extend `InvalidArgumentException`; `AccessDenied` extends `RuntimeException`.
The bound of 100 bytes is `Presence::MAX_CODE_LENGTH`, the length of a string in the context of an activity of
`tilttek/activity`; the bound of a count is `AttendanceCriterion::MAX`.

A scan that is not an admission — unknown to the ledger, with another outcome than admitted, or in conflict between
the device and the server — is not an error: `AttendanceRecorder::record()` returns `false`, as it does when the
`PresenceResolver` returns `null`.

The exceptions of `tilttek/activity` reach the caller unchanged: `UnknownVerb` when the journal declares its verbs
without `attendance.*`, `InvalidActivity` when the scan is dated outside the journal's window, and
`IdempotencyConflict` when a scan is resolved to another presence than the first time.
