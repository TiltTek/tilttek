# The badges in the database

| Table | One row per | Columns |
|---|---|---|
| `tilttek_badges_contribution` | badge, path and activity | `badge`, `path`, `activity`, `member`, `quantity`, `occurred_at` |
| `tilttek_badges_award` | member, badge and number | `badge`, `member`, `number`, `awarded_at`, `cause`, `manual` |
| `tilttek_badges_profile` | member who shows a badge | `member`, `shown` (JSON), `featured` (JSON), `title` |
| `tilttek_badges_grant` | member and application | `member`, `client`, `granted_at` |

A contribution, an award or a consent is inserted by a statement that writes nothing when its row is already there,
without aborting the surrounding transaction: `ON CONFLICT DO NOTHING` on PostgreSQL, `INSERT IGNORE` on MySQL and
MariaDB, `INSERT OR IGNORE` on SQLite. On another platform, a plain insertion lets the unique violation of a race reach
the caller. A cancellation reads and deletes the activity's contributions in one transaction; a profile is replaced in
one transaction. Dates are written in UTC, to the second.

Members are written as their references, `member:44`, up to 320 bytes.
