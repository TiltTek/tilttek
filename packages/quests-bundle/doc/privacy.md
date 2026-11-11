# Privacy

With `phil/privacy-bundle` installed, the bundle registers the module `tilttek_quests`. The data subject is a member,
referenced as `<member_kind>:<value>` — `member:44` by default.

**The export** lists what the member's activity brought to each quest, their acceptances and abandonments, the quests
they accomplished themselves and their duels; it is empty when the quests keep nothing of them.

**The erasure** deletes all of it from four tables:

| Table | Effect |
|---|---|
| `tilttek_quests_contribution` | Deleted |
| `tilttek_quests_enrolment` | Deleted |
| `tilttek_quests_completion` | Deleted — the member's own completions |
| `tilttek_quests_duel` | Deleted — the member's duels, as either side |

What a team or the community accomplished stays: it is not the member's, and its rewards were given. Nothing public is
shown of a member by the bundle; showing a member's quests on a profile is the application's choice and the member's.
