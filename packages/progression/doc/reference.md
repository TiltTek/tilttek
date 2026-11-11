# Reference

| Type | Role |
|---|---|
| `Progression` | Standings, seasons past, milestones, gains, streaks, freezes, sweep, erasure |
| `ProgressionProjection` | The activity turned into experience and streaks, cancellations, rebuilds |
| `ProgressionObserver` | Told of the level changes, milestones and broken streaks |
| `Curve`, `Level` | The experience each level starts at; where experience stands |
| `Track`, `Tracks`, `Subject` | A line of progression and whom it rewards; the tracks of an application |
| `Milestone`, `ReachedMilestone` | A step worth a reward; a step reached |
| `LevelChange` | A subject going from one level to another |
| `Standing`, `SeasonResult` | Where a subject stands now; what it reached in a season past |
| `Erasure` | What erasing a member removed |
| `Names` | The names of tracks, rules, milestones and streaks |
| `Experience\ExperienceRule` | Experience for a gesture |
| `Experience\Cap`, `Experience\Period` | The most a rule gives a subject over a period |
| `Experience\Gain` | The experience an activity brought a subject by a rule |
| `Season\Season`, `Season\SeasonSchedule` | A season; which season a moment falls in |
| `Season\CalendarSeasons`, `Season\SeasonLength` | Seasons by month, quarter or year |
| `Season\NamedSeasons` | Named and dated seasons |
| `Streak\StreakRule`, `Streak\StreakRules` | A streak; the streaks of an application |
| `Streak\StreakUnit`, `Streak\Grace` | Days or weeks; the missed units forgiven |
| `Streak\StreakCalculator`, `Streak\StreakStatus` | Counting the runs; where a member stands with a streak |
| `Streak\StreakKeeper` | The gestures, the states, the frozen units and the sweep |
| `Streak\StreakState`, `Streak\StreakGesture`, `Streak\StreakBroken` | A streak's state; an active unit; a run broken |
| `Streak\TimezoneResolver`, `Streak\FixedTimezone`, `Streak\ContextTimezone` | The timezone of a member's day |
| `Store\ExperienceStore`, `Store\InMemoryExperienceStore`, `Store\Total` | Where the gains are kept; a total |
| `Store\MilestoneStore`, `Store\InMemoryMilestoneStore` | Where the milestones reached are kept |
| `Store\StreakStore`, `Store\InMemoryStreakStore` | Where the streaks are kept |
| `Exception\ProgressionException` and its three exceptions | [Exceptions](exceptions.md) |
