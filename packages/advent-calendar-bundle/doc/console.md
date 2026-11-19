# Console

The commands are registered when `symfony/console` is installed.

## `tilttek:advent-calendar:list`

Lists the published calendars, those of the configuration and those of the database: their identifier, title,
timezone, number of boxes, first and closing dates, and opening policy.

## `tilttek:advent-calendar:show <calendar>`

Shows the boxes of a published calendar: each box's date, the first and the last day it may be opened, its state now
for a member who has not opened it, and its surprise. It reads no member's openings.

```text
 Box   Date         First day    Last day     Now        Surprise
 3     2026-12-03   2026-12-03   2026-12-24   openable   points points × 10
```

## `tilttek:advent-calendar:check`

Checks every calendar of the database, published or not, as the library reads it, and names those whose fields no
longer hold together with the reason. It fails when one does, so that a deploy or a scheduled task sees a broken
calendar before a member does. It is registered with the ORM and the `doctrine` store.
