# Writing a store

The progression is kept in three stores. An application that does not use the bundle implements them on its storage;
each must pass the contract test case the library's tests carry.

## `ExperienceStore`

| Method | Contract |
|---|---|
| `add(Gain)` | Writes the gain once per track, rule and activity; false when it is there |
| `remove(activity)` | Removes and returns every gain of the activity, as a list |
| `total(track, subject, season)` | The sum of the subject's gains on the track in the season, the empty code for none |
| `lifetime(track, subject)` | The sum of every season |
| `seasons(track, subject)` | A `Total` per season, those outside any season left out |
| `earned(track, rule, subject, since, until)` | What the rule gave from `since`, included, to `until`, excluded |
| `totalsSince(since)` | The full totals of every track, subject and season with a gain from `since` on |
| `of(subject)` | The subject's gains, in the order they occurred |
| `forget(since)` | Deletes the gains from `since` on, all when null |
| `eraseMember(member)` | Deletes the gains whose subject or member is the member |

## `MilestoneStore`

`reach()` writes a `ReachedMilestone` once per track, milestone, subject and season; `of()` lists a subject's in the
order they were reached; `eraseMember()` deletes those whose subject or member is the member.

## `StreakStore`

`add()` writes a `StreakGesture` once per streak and activity; `remove()` returns and deletes an activity's gestures;
`units()` lists a member's active units, sorted, each once; `freeze()`, `thaw()` and `frozen()` keep the frozen units;
`state()`, `save()`, `discard()` keep one `StreakState` per streak and member; `alive(streak, before)` lists the states
with a run under way whose last active unit is before `before`; `forget()` deletes the gestures from a moment on, and
`eraseMember()` everything of a member.

The gains and gestures are written once even when two processes write them at the same moment: a unique key on the
track, rule and activity — the streak and activity — and an insertion that writes nothing on a duplicate.
