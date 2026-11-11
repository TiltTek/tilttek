# Erasure

`Quests::export($member)` reads what the quests keep of a member: their contributions, their enrolments, the quests
they accomplished themselves and their duels.

`Quests::erase($member)` deletes all of it, and returns an `Erasure` with the count of each:

- **the member's contributions** — the progress of a team or of the community goes back by what their activity
  brought;
- **their enrolments**, under whatever team;
- **the quests they accomplished themselves**, duels won included;
- **their duels**, as challenger or as the member challenged — the other side's duel goes with them.

What a team or the community accomplished stays in the journal of accomplished quests: it is not the member's, and its
rewards were given. The journal of activity is erased by `tilttek/activity`.
