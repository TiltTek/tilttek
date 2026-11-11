# The projection

`QuestProjection` is a projection of `tilttek/activity`: the `Projector` hands it the entries of the journal it has
not seen, in order.

## A recorded activity

For each quest whose steps accept the activity:

1. the quest must be open at the activity's moment, and the member's activity must count for it — always for a quest
   followed automatically, within the member's enrolment for a quest accepted, within one of the member's accepted
   duels for a duel;
2. a `Contribution` is written for each step that accepts it — once per quest, step and activity, even when the journal
   hands the activity twice;
3. the quest is evaluated for its subject over its occurrence, from the moment the quest it follows was accomplished;
   when its last step is done, a `Completion` is written and the observer is told, with the rewards and the members
   they go to; the quests that follow it are evaluated in turn;
4. for a duel, each of the member's accepted duels the activity falls in is evaluated, and the first side to accomplish
   the quest wins it.

## A cancellation

A cancelled activity's contributions are removed: a quest under way goes back. **A quest accomplished stays
accomplished**, its rewards given — the application decides whether to take them back.

## Rebuilds

When a rule changes — a step, a target, a schedule —, `Projector::rebuild()` resets the projection from a moment:
the contributions and the completions from that moment on are forgotten, the duels won since are accepted again, and
the activity is replayed under the new catalog. **A quest accomplished again during the rebuild is not told a second
time**; one that is not is withdrawn silently. Enrolments and duels are decisions of the members, not derived from the
journal: a rebuild keeps them as they are now.

## What it tells

`QuestObserver` receives:

| Method | When |
|---|---|
| `completed(QuestCompleted)` | A quest is accomplished: the quest, the completion, the rewards and their members |
| `duelWon(Duel)` | A side accomplished a duel first — after its `completed()` |
| `duelExpired(Duel)` | A duel's time ran out, or its offer went unanswered — told by `Duels` |
