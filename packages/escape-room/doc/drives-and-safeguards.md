# Drives and safeguards

Tilttek sorts each package by the drives it touches, and every drive that puts pressure on players carries a
safeguard. This library stands under three:

| Drive | What the library does | Safeguard |
|---|---|---|
| Curiosity and autonomy | Puzzles from `tilttek/puzzle-builder`, hints asked when the team wants them | Nothing beyond the common rules |
| Scarcity and urgency | The countdown of the room | The countdown is the game the team booked, known before it starts; the host pauses it; a hint adds to the final time, never to the countdown, so asking never brings the end closer |
| Social comparison | The leaderboard of a room | Private by default: a team is shown only once it agrees, and may withdraw at any time; the hints are shown beside the time |

## The common rules it keeps

- **A member is an opaque reference.** A team, a party and a host are `ResourceReference`s given by the application;
  the library reads no name, email or account, and refuses a team or a party that looks like an email address.
- **Everything replays.** A game is the record of its moves — start, pauses, solves, hints, end —, and its timer and
  final time follow from them.
- **Nothing is public without the members' choice.** Only the leaderboard is public, and only by consent; erasing a
  team erases its games and results from the application's storage, the library keeping none.
- **No pressure to pay.** Hints have a cost in seconds, never in money; no time is sold, and the library has no notion
  of a price.
