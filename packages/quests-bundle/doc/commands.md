# Commands

| Command | Does |
|---|---|
| `tilttek:quests:list` | Lists the quests of the catalog: kind, admission, steps — joined by an arrow when ordered —, the quest each follows and the rewards |
| `tilttek:quests:show member:44` | Shows where every quest stands for a member, step by step, with their team |
| `tilttek:quests:expire` | Expires the duel offers left unanswered and the duels whose time is up with no winner, dispatching `DuelExpired` for each |

Run `tilttek:quests:expire` every few minutes from the application's scheduler; nothing else needs a schedule, since
a quest's dates are read when an activity happens.
