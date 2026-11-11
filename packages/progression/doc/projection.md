# The projection

`ProgressionProjection` is a projection of `tilttek/activity`: the journal hands it each entry its filter reads — the
verbs of every track's rules and every streak —, once in normal operation.

## A recorded activity

For each track whose rules match, by name, and whose subject the activity has:

1. the season is the one the activity fell in, empty outside any season;
2. the experience is the rule's, limited by its cap in the period of the activity;
3. the gain is written once per track, rule and activity — an entry handed twice brings nothing more;
4. a level crossed is told to `ProgressionObserver::leveledUp()` with a `LevelChange`, from the level before to the
   level after, even several levels at once;
5. each milestone passed is reached and told, once.

For each streak whose rule matches, the member's unit — in their timezone — is made active and the streak's state
brought up to the moment the journal wrote the entry: a run found over is told.

## A cancellation

Every gain of the cancelled activity is taken back; a subject whose level falls is told by `leveledDown()`. A
milestone reached stays reached. The streak gestures of the activity are removed and the states recounted silently.

## Rebuilds

When a rule, a curve or a season changes, `Projector::rebuild($projection, $since)` resets the projection — the gains
and the streak gestures from `since` on are forgotten, all of them when `since` is null — and replays the journal of
that period under the new configuration. Milestones are kept. While it replays the entries written before the reset,
the projection tells only the levels above those the subjects held before it, tells no level lost and no broken
streak: the members were told then. An entry written at the reset or after is told as usual.

The level tells are the projection's own: a level reached and lost again in the same replay is told once, and the
cancellation that takes it back, being a replay, is not.
