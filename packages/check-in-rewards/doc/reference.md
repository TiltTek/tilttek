# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `AttendanceRecorder` | Records a scan's admission once, read from the ledger, with its first session, day, place and week |
| `Contract\PresenceResolver` | What the application says of a check-in: the presence, or null for an admission that rewards no one |
| `Presence` | The member, the session, the event, the place, the track and the timezone of an admission |
| `AttendanceVerbs` | The verbs the library records, and their pattern `attendance.*` |
| `AttendanceCriterion` | An attendance rule — sessions, every day, distinct places, one place, weeks — for badges and quests |
| `AttendanceReader` | A member's attendance, read by that member only |
| `AttendanceSummary` | Distinct sessions, days and places, of an event or of all, and weeks attended |
| `Exception\CheckInRewardsException` | Every exception of the library |
| `Exception\InvalidPresence` | A presence whose event, place or track does not fit in the context of an activity |
| `Exception\InvalidCriterion` | A criterion whose count is out of bounds, or whose event or place does not fit |
| `Exception\AccessDenied` | A viewer asking for the attendance of another member |
