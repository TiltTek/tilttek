# Database

| Table | One row per | Key | Holds |
|---|---|---|---|
| `tilttek_progression_gain` | track, rule and activity | `track, rule_name, activity` | the subject, the member, the amount, the moment, the season |
| `tilttek_progression_milestone` | subject, track, milestone and season | `subject, track, milestone, season` | the member, the moment, the activity |
| `tilttek_progression_streak_gesture` | streak and activity | `streak, activity` | the member, the unit, the moment |
| `tilttek_progression_streak_freeze` | member, streak and unit | `member, streak, unit` | — |
| `tilttek_progression_streak_state` | member and streak | `member, streak` | the current and best runs, the run's start, the last active unit, the timezone |

The tables are added to the schema the ORM generates for the configured connection by `ProgressionSchemaListener`, so
`doctrine:migrations:diff` writes their migration and `doctrine:schema:validate` does not ask to drop them.

Gains, milestones, gestures and frozen units are inserted by a statement that writes nothing when the row is there —
`ON CONFLICT DO NOTHING` on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite; elsewhere a
plain insertion, whose unique violation reaches the caller —, so an activity counts once even when two processes write
it. Totals are sums, never fields. Dates are written in UTC, to the second; units are day or week numbers.
