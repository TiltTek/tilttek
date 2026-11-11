# Commands

| Command | Does |
|---|---|
| `tilttek:progression:sweep` | Finds the streaks broken since the members' last gestures and dispatches `StreakBroken` for each, once: `3 streaks broke.` |
| `tilttek:progression:show <subject>` | Shows where a member — or a character — stands on every track (season, level, experience, next level, lifetime) and with every streak |

Run the sweep once an hour from the scheduler: the members' days end at different hours. A subject that is not a
`kind:identifier` reference fails with an error.
