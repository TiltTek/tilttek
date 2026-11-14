# Writing a store

An application keeps the badges on its own storage by implementing the four stores. Each contract is the behaviour the
library's tests check on the implementations in memory, and that `tilttek/badges-bundle` checks on Doctrine DBAL.

## `ProgressStore`

- `add()` keeps a contribution once per badge, path and activity, and returns false for one already there — even when
  two processes write it at the same moment.
- `remove($activity)` removes every contribution of the activity and returns them, as a list.
- `count()`, `quantity()` and `moments()` read one member's contributions to one path of one badge.
- `forget($since)` removes the contributions of the activities from `since` on, all of them when null.
- `eraseMember()` removes the member's contributions.

## `AwardStore`

- `add()` keeps an award once per badge, member and number, and returns false for one already there, held or
  withdrawn — even when two processes write it at the same moment. An award not made by hand is kept with its notice
  due; an award made by hand is owed none.
- `markDelivered()` notes that the notice of the award was given; `undelivered($member)` lists the member's awards
  whose notice is due, by badge and number.
- `withdraw()` takes the award out of those the member holds and returns true: it waits for the notice of its
  withdrawal. An award whose own notice is still due is removed at once and owed no notice, and an award the member
  does not hold is left alone: false for both. `withdrawn($member)` lists the withdrawn awards whose notice is due, by
  badge and number.
- `remove()` removes the award of that badge, member and number, held or withdrawn.
- `of($member, $badge)` lists the awards the member holds — of one badge when it is named — by the moment they were
  earned, then badge and number; withdrawn awards are left out.
- `forgetDerived($since)` removes the awards not made by hand earned from `since` on, all of them when null, held or
  withdrawn, and returns those held whose notice was given.
- `eraseMember()` removes the member's awards, held or withdrawn.

## `ProfileStore`

- `get()` returns the member's profile, an empty one when there is none.
- `save()` keeps a profile and removes an empty one.
- `erase()` removes it, and says whether there was one.

## `GrantStore`

- `grant()` keeps a consent; given again, it stays given.
- `withdraw()` removes it, and says whether there was one; `allows()` says whether it is there.
- `clients()` lists the applications the member allowed, by name.
- `eraseMember()` removes the member's consents.
