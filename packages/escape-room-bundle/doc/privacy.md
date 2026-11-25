# Privacy

With `phil/privacy-bundle` installed, `EscapeRoomPrivacy` takes part in an access or erasure request, under the module
`tilttek_escape_room`. The data subject is a member, referenced under the kind `privacy.member_kind` — `member` by
default.

## Export

| Part | What it holds |
|---|---|
| `games` | The games the member played as the team, as the plain values the library writes |
| `results` | Their results: the room, the session, the times, the hints, the skips, the escape and the consent |
| `screens` | The hint screens they showed last as a host: the kind, the moment, the puzzle and tier, the message |
| `bookings` | The bookings they made as the party: the identifier, the session, the seats, and whether it is private |

A part without rows is left out; a member with none gets an empty export.

## Erasure

The erasure decides column by column. Where a row stays, it holds a pseudonym the erasure draws for itself — `erased:`
and 32 random hexadecimal digits — which nothing in the bundle joins back to the member.

| Column | Effect | Why |
|---|---|---|
| `tilttek_escape_room_game.team` | Deleted, or pseudonymised while a game runs | A game over serves nobody once its result is recorded, so it is deleted. A game still running is the room's live session: its countdown, console and screen keep working under the pseudonym, with the consent to show the result withdrawn, and the game ends as usual. |
| `tilttek_escape_room_result.team` | Deleted, or pseudonymised while a game runs | A result is read only by the leaderboard, and only when the team agreed to show it. Withdrawn, it serves nothing; kept, its room, session and moment to the microsecond would point back to the booking. Deleting it leaves the places of the other teams as they are. A game still running records its result under the pseudonym, hidden from the leaderboard. |
| `tilttek_escape_room_screen.game` | Deleted, or pseudonymised while a game runs | A hint screen serves while its game runs: the screens of the member's games over are deleted with them, and the message of the host with them. |
| `tilttek_escape_room_screen.host` | Deleted, or pseudonymised while a game runs | A screen the member showed as a host is deleted once its game is over or gone. While the game runs, the team still reads the hint: the screen stays with the pseudonym as its host. |
| `tilttek_escape_room_booking.party` | Pseudonymised | A booking holds seats of a session that other parties share: deleting it would hand its seats out again and change the order of the session. It stays with the pseudonym. Its identifier is the application's, and may join the site's orders or payments, so the row is still personal data for whoever holds them: the application deletes the bookings of past sessions by its own retention. |

The report names each column with its effect: `deleted` when nothing of the member is left running,
`pseudonymised` when a game of theirs still runs or for the bookings. The lock of a session,
`tilttek_escape_room_session_lock`, names nobody and is not in the report.

A reference of the kind `erased` given by the caller — as a team, a party or a host — is refused by the library with
`InvalidRoom`: only an erasure writes one.

## What is never kept

The updates of Mercure name the game and the move only. The Messenger message of the schedule, `ExpireDue`, carries
nothing. A team, a party and a host are named by stable identifiers, never by an email address.
