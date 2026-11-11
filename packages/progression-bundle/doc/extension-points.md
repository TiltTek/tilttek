# Extension points

- **A store.** Alias `Tilttek\Progression\Store\ExperienceStore`, `MilestoneStore` or `StreakStore` to your own
  service; it must pass the library's contract test case.
- **A member's timezone.** `timezone_context` reads it from the activity's context. To read it elsewhere — never from
  something that changes after the gesture, or rebuilds give other days —, define the service
  `Tilttek\Progression\Streak\TimezoneResolver`.
- **The observer.** The projection and the keeper tell `DispatchingObserver`; listen to its events, or decorate it.
- **The tracks or the streaks.** Replace the `Tilttek\Progression\Tracks` or `Tilttek\Progression\Streak\StreakRules`
  service — from a database, for instance — and rebuild the projection when they change.
