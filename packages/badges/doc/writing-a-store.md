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

- `add()` keeps an award once per badge, member and number, and returns false for one already there.
- `remove()` removes the award of that badge, member and number.
- `of($member, $badge)` lists the member's awards — of one badge when it is named — by the moment they were earned,
  then badge and number.
- `forgetDerived($since)` removes and returns the awards not made by hand earned from `since` on, all of them when null.
- `eraseMember()` removes the member's awards.

## `ProfileStore`

- `get()` returns the member's profile, an empty one when there is none.
- `save()` keeps a profile and removes an empty one.
- `erase()` removes it, and says whether there was one.

## `GrantStore`

- `grant()` keeps a consent; given again, it stays given.
- `withdraw()` removes it, and says whether there was one; `allows()` says whether it is there.
- `clients()` lists the applications the member allowed, by name.
- `eraseMember()` removes the member's consents.
