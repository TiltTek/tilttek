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

The erasure draws one pseudonym of its own — `erased:` and 32 random hexadecimal digits — and writes it in place of the
member in the games, the results, the screens and the bookings; their results are withdrawn from the leaderboard. The
times, the hints and the seats stay, so the other teams keep their places and the sessions their seats. The report
names each column as `pseudonymised`.

A reference of the kind `erased` given by the caller — as a team, a party or a host — is refused by the library with
`InvalidRoom`: only an erasure writes one.

## What is never kept

The updates of Mercure name the game and the move only. The Messenger message of the schedule, `ExpireDue`, carries
nothing. A team, a party and a host are named by stable identifiers, never by an email address.
