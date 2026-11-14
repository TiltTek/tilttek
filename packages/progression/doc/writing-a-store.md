# Writing a store

The progression is kept in three stores. An application that does not use the bundle implements them on its storage;
each must pass the contract test case the library's tests carry.

## `ExperienceStore`

| Method | Contract |
|---|---|
| `add(Gain)` | Writes the gain once per track, rule and activity, its notice due; false when it is there |
| `undelivered(subject)` | The subject's gains whose notice is due, in the order they occurred |
| `markDelivered(Gain)` | Notes the gain's notice given; nothing when the gain is not there |
| `remove(activity)` | Removes and returns every gain of the activity, as a list |
| `total(track, subject, season)` | The sum of the subject's gains on the track in the season, the empty code for none |
| `lifetime(track, subject)` | The sum of every season |
| `seasons(track, subject)` | A `Total` per season, those outside any season left out |
| `earned(track, rule, subject, since, until)` | What the rule gave from `since`, included, to `until`, excluded |
| `totalsSince(since)` | The full totals of every track, subject and season with a gain from `since` on |
| `of(subject)` | The subject's gains, in the order they occurred |
| `ofMember(member)` | The gains whose subject or member is the member, in the order they occurred, for an export |
| `forget(since)` | Deletes the gains from `since` on, all when null |
| `eraseMember(member)` | Deletes the gains whose subject or member is the member |

## `MilestoneStore`

`reach()` writes a `ReachedMilestone` once per track, milestone, subject and season, its notice due; `undelivered()`
lists a subject's whose notice is due and `markDelivered()` notes one given; `of()` lists a subject's in the order
they were reached, `ofMember()` those whose subject or member is the member, for an export; `eraseMember()` deletes
those. On a database, a `delivered` boolean column on each of the two tables keeps the notice: true by default for
the rows written before it, false on insertion, and turned true by an update conditional on it.

## `StreakStore`

`add()` writes a `StreakGesture` once per streak and activity; `remove()` returns and deletes an activity's gestures;
`units()` lists a member's active units, sorted, each once; `freeze()`, `thaw()` and `frozen()` keep the frozen units;
`state()`, `save()`, `discard()` keep one `StreakState` per streak and member; `alive(streak, before)` lists the states
with a run under way whose last active unit is before `before`; `forget()` deletes the gestures from a moment on, and
`eraseMember()` everything of a member.

The gains and gestures are written once even when two processes write them at the same moment: a unique key on the
track, rule and activity — the streak and activity — and an insertion that writes nothing on a duplicate.
