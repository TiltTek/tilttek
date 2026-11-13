# Drives and safeguards

Tilttek sorts each package by the drives it touches, and every drive that puts pressure on players carries a
safeguard. This library stands under one drive:

| Drive | What the library does | Safeguard |
|---|---|---|
| Curiosity and autonomy | Puzzles to solve in an order the players choose among those open, hints asked when the players want them | Nothing beyond the common rules |

The library adds no pressure of its own: no countdown, no score, no ranking, no streak. A game built on it that adds one
— the countdown of an escape room, a leaderboard — carries that drive's safeguard itself.

## The common rules it keeps

- **A member is an opaque reference.** A team is a `ResourceReference` given by the application; the library reads no
  name, email or account, and refuses a reference that looks like an email address.
- **Everything replays.** A progress is the record of the moves — solves with their time, hints with their tier and
  time, guesses counted —, so a game's state follows from what was played.
- **Nothing is public without the members' choice.** The library publishes nothing; its view is for the team's own
  screen.
- **No pressure to pay.** Hints are revealed tier after tier, and their penalty is a number of seconds the game decides
  what to do with; the library has no notion of a price.
