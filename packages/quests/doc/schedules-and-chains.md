# Schedules and chains

## When a quest is open

A quest's `Schedule` says, for any moment, the `Occurrence` it falls in, or null when the quest is closed:

- `Permanent` — always open, one occurrence whose code is empty;
- `Dated` — open from its opening, included, to its closing, excluded; either may be left open, not both;
- a schedule of the application's own — dates that come back each year, read from the definition by a
  `ScheduleReader` given to `QuestReader`. `tilttek/quests-bundle` brings one on `phil/almanac`, under the key
  `recurs`.

An occurrence has a code — empty for a quest open once, the date it opens on for one that comes back —, an opening and a
closing. **A quest is accomplished once per occurrence and subject**: a quest that comes back each Easter is accomplished
again each year, and its progress in a year counts only that year's activity.

`QuestReader` reads `opens` and `closes` in its timezone: midnight in Toronto is not midnight in UTC.

## Chains

`after` names the quest a quest follows. **Accomplishing the first opens the second for the same subject**: only the
activity from that moment on counts for it, and the projection evaluates it at once with the activity that opened it.
For a quest that follows one that comes back, the latest accomplishment opens it.

A catalog checks every chain when it is built: the quest followed exists, it is of the same kind — a team's chain stays
a team's —, and no chain loops back. A duel follows no quest.
