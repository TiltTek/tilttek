# Database

The bundle adds five tables to the schema the ORM generates for its connection; without the ORM, create them from
`Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomTables::configure($schema)`. Moments are written by the type
`datetime_utc_immutable`, in UTC, to the second.

## `tilttek_escape_room_game`

| Column | Holds |
|---|---|
| `id` | The game's identifier, 64 characters at most; the primary key |
| `room`, `team`, `session` | The room, the team's reference and the session, beside the state for the queries |
| `deadline` | When the countdown reaches zero; null while the game is paused or over; indexed |
| `state` | The JSON of the plain values `Game::toArray()` writes, every time in UTC to the microsecond |

## `tilttek_escape_room_result`

| Column | Holds |
|---|---|
| `game` | The game; the primary key |
| `room`, `team`, `session` | As above |
| `final_seconds`, `played_seconds`, `penalty_seconds`, `hints`, `skips` | The result |
| `escaped_at`, `escaped_micro` | The moment of the escape, and its microseconds, which order two teams that escaped within the same second |
| `public` | Whether the team agreed to show it |

The index `tilttek_escape_room_result_rank` reads the leaderboard of a room in order.

## `tilttek_escape_room_screen`

One row per game: what the hint screen shows — `kind` (`hint`, `message` or `cleared`), the `host` who showed it,
`shown_at`, and the `puzzle`, `tier` and `penalty` of a hint or the `message`, 280 characters at most.

## `tilttek_escape_room_booking`

The bookings of the sessions: `id`, `session`, `room`, `party`, `seats`, `private`, `booked_at`, and `position`, the
order of the booking in its session.

## `tilttek_escape_room_session_lock`

One row per session booked, written by each booking before it decides: the lock of [the bookings](bookings.md).
