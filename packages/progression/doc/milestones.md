# Milestones

A `Milestone` is a step of a track worth a reward: an amount of experience (`new Milestone('thousand', 1000)`) or a
level (`new Milestone('veteran', 10, level: true)`). A level milestone must be on the track's curve.

When an activity takes a subject past a milestone's threshold — above the experience it had, at most the experience it
reaches —, the projection keeps a `ReachedMilestone` and tells `ProgressionObserver::milestoneReached()`. A milestone is
reached once per track, subject and season: on a seasonal track, it can be reached again the next season.

The library gives no reward itself — it depends on no other gamification package. The application listens and gives
what it decided: points through `tilttek/points`, an object, a title, a badge awarded by hand.

A milestone reached stays reached: a cancellation that takes the subject back under it does not withdraw it, and a
rebuild neither forgets it nor tells it again, since its reward was given. Erasing a member removes their milestones.
`$progression->milestones($subject)` lists them in the order they were reached.
