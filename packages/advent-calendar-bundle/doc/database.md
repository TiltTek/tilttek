# Database

## `tilttek_advent_calendar_opening`

The openings, through Doctrine DBAL. The table is added to the schema the ORM generates for the configured connection
by `OpeningsSchemaListener`, so that `doctrine:migrations:diff` writes its migration and `doctrine:schema:validate`
does not ask to drop it. Without the ORM, create it from `AdventCalendarTables::configure()`.

| Column | Type | Holds |
|---|---|---|
| `calendar` | `string(64)` | The calendar's identifier. |
| `member` | `string(320)` | The member's reference, `member:44`. |
| `day` | `smallint` | The box. |
| `opened_at` | `datetime_immutable` | When it was first opened, in UTC, to the second. |
| `delivered` | `boolean`, false by default | Whether its `BoxOpened` was dispatched without an exception. |

The primary key `(calendar, member, day)` is the guard that makes an opening happen once: two requests that open the
same box at once both insert, one wins, and the other is told the box was opened already. The insert runs in a
transaction of its own, nested in the caller's, so that the refused insert never spoils a transaction
PostgreSQL would otherwise abort. The index `tilttek_advent_calendar_opening_member` serves a member's export and
erasure.

An opening is inserted with `delivered` false. Once its `BoxOpened` was dispatched without an exception, one
conditional update turns it true; a listener that failed leaves it false, and the next opening of the calendar by the
member, or `AdventCalendar::deliver()`, dispatches it again. Listeners of `BoxOpened` are therefore idempotent.

## `tilttek_advent_calendar`

The calendars the site edits, mapped by the ORM to `CalendarRecord`: `id`, `title`, `timezone`, `first_date`,
`closing_date`, `policy`, `catch_up_days`, `published`, and `surprises` as JSON, one per box in the order of the days.
The table exists only with the ORM.
