# Awarding

## The projection

`BadgeProjection` is a projection of `tilttek/activity`: the application registers it with its other projections, and
the journal's `Projector` hands it the entries it has not seen, live or by a scheduled task.

For each recorded activity, every leaf of every badge that accepts it gets a contribution — once per badge, path and
activity, so an entry handed twice brings nothing more —, and the badges touched are evaluated for the member:

- a badge whose target is reached is awarded, its `Award` numbered 1 and dated at the moment of the activity that
  reached it, its cause the activity's idempotency key;
- a repeatable badge is awarded again each time its target is reached again, numbered 2, 3…; one activity brings at
  most `BadgeProjection::MAX_AWARDS_PER_ACTIVITY` awards of it, 10, or the bound given to the projection, and the
  awards still due come with the member's next activities for that badge, so that a single huge quantity can neither
  fill the award store nor flood the observer;
- a badge that is not repeatable and is already held by hand is left as it is.

For a cancellation, the activity's contributions are removed and the badges they touched are evaluated again: a
revocable badge whose target is no longer reached is withdrawn, its highest numbers first. A badge that is not
revocable is kept.

## Progress

`Badges::progress($member, 'commenter')` reads a `Progress`: `current`, `target`, `shown()` — the current value at most
the target, *7 of 10* —, `isReached()` and `times()`. `Badges::overview($member)` gives a `Standing` per badge of the
catalog, in its order, with its progress and how many times the member holds it; a hidden badge they have not earned is
left out.

## By hand

`Badges::award($member, 'organiser', 'Ran the convention')` awards a badge by hand, whatever its criterion, dated by the
clock; a badge that is not repeatable and is already held is not awarded again. `Badges::revoke()` withdraws the awards
made by hand; those earned by the activity stay, since the activity would earn them again.

## Observers

An `AwardObserver` passed to the projection is told `awarded()` and `revoked()`, to notify the member or show the badge
at once. An award made by hand is the application's own act and is not told.

## When a rule changes

`Projector::rebuild($projection, $since)` forgets the contributions of the activities from `since` on — all of them
when `since` is null — and the awards derived from them, then replays the journal under the new catalog. An award earned
again with the same badge, member and number during the rebuild is not told again; an award the new rules no longer
give is withdrawn without being told. Awards made by hand are kept. A `Measure` is read again as it is when the rebuild
runs.
