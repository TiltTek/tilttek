# Database

The bundle adds four tables to the schema the ORM generates for its connection, through `TableSafetySchemaListener`:

| Table | Key | Holds |
|---|---|---|
| `tilttek_table_safety_table` | `table_ref` | each table's settings, as JSON |
| `tilttek_table_safety_participation` | `table_ref`, `participant` | each participant's lines and veils, checklist and session zero, as JSON, when they first answered and when they last changed |
| `tilttek_table_safety_signal` | `id` | the signals: table, kind, audience, the moment and its microsecond, the author of a named signal, when it was seen |
| `tilttek_table_safety_feedback` | `table_ref`, `session`, `participant` | each participant's stars and wishes of each session, as JSON |

The participations and the feedback are indexed by participant, for an export or an erasure; the signals by table and
microsecond, for the last ones of a table, and by author. An anonymous signal's author is null. The moments are written
in UTC, to the second; the microsecond keeps the order of two signals of the same second.
