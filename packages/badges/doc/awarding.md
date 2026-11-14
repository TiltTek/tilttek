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

Two awards by hand made at the same moment read the same awards and choose the same number; the store keeps one of
them. The other reads the awards again. For a badge that is not repeatable, it returns the award the store kept, so
both callers hold the same award. For a repeatable badge, both awards are wanted: it takes the next number, at most
`Badges::AWARD_ATTEMPTS` times, 5, and raises `AwardConflict` when other awards took the number at every attempt.

## Observers

An `AwardObserver` passed to the projection is told `awarded()` and `revoked()`, to notify the member or show the badge
at once. An award made by hand is the application's own act and is not told.

The observer is told at least once. An award earned by the activity is kept with its notice due, and the store notes it
delivered once the observer has been told; an award withdrawn leaves the member's awards at once, but stays in the store
until the observer has been told of its withdrawal, and is removed then. When the observer fails, the notice stays due,
and is given:

- when the same entry is handed to the projection again: the request repeated under the same idempotency key, whose
  entry the journal hands again to the projections still before it, or the next catch-up;
- before the member's next activity or cancellation is evaluated;
- by `BadgeProjection::deliver($member)`, which gives every notice the member is owed, withdrawals first, and returns
  how many it settled. A notice of a badge the catalog no longer holds is let go without the observer.

An award withdrawn before its own notice was given is owed no notice at all. Since a notice can be given again after a
failure, an observer must be idempotent: it recognises an award by its badge, member and number, and does nothing for a
notice it has already acted on.

## When a rule changes

`Projector::rebuild($projection, $since)` forgets the contributions of the activities from `since` on — all of them
when `since` is null — and the awards derived from them, then replays the journal under the new catalog. An award earned
again with the same badge, member and number during the rebuild is not told again, unless its first notice was never
given; an award the new rules no longer give is withdrawn without being told. Awards made by hand are kept. A
`Measure` is read again as it is when the rebuild runs.
