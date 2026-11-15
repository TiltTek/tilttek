# Commands

## `tilttek:leaderboards:snapshot`

```sh
bin/console tilttek:leaderboards:snapshot
bin/console tilttek:leaderboards:snapshot games-month 2026-10
```

Without arguments, it takes the snapshot of the period each board ended last, when it has none yet — to run from a
scheduler soon after midnight —; with a board and a period, the snapshot of that one. It lists the snapshots taken, with
how many members were ranked and kept. A board without its period, or a period without its board, is refused; a period
not over, an unknown board or period fails with the library's message.

## `tilttek:leaderboards:settle`

```sh
bin/console tilttek:leaderboards:settle
bin/console tilttek:leaderboards:settle weekly-xp 2026-w45
```

Without arguments, it settles every week over that a board with a league has not settled, the earliest first — to run
from a scheduler soon after the week ends —; with a board and a week, that one. It lists the weeks settled, with how
many members took part and how many went up and down; a week already settled is not settled again. A board without its
week, or a week without its board, is refused; a week not over, a board without a league, an unknown board or week
fails with the library's message.

## `tilttek:leaderboards:slide`

```sh
bin/console tilttek:leaderboards:slide
```

Takes out of each board ranked over a sliding window the gestures older than its first day, and counts again the scores
they had made — to run from a scheduler soon after midnight. It says how many contributions left their window.

## `tilttek:leaderboards:show`

```sh
bin/console tilttek:leaderboards:show games-month 2026-10 --limit=20
```

Shows a board in a period — the one under way by default — as the public sees it: the rank, the member's reference for
one who chose to appear under their name, the pseudonym for one who chose one, a dash for the others, and the score.
