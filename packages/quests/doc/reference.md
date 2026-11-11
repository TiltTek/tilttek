# Reference

| Type | Role |
|---|---|
| `Quests` | Acceptance, abandonment, status, board, journal of accomplished quests, export, erasure |
| `QuestProjection` | The activity turned into progress, quests accomplished, chains, duels decided, cancellations, rebuilds |
| `QuestObserver`, `QuestCompleted` | Told of the quests accomplished with their rewards, the duels won and expired |
| `Evaluator` | The catalog, the stores, and how a subject's progress is read |
| `Quest`, `QuestKind`, `Admission` | A quest; its genre; how a member comes to follow it |
| `Step`, `Scope`, `StepMeasure` | A step; the activities it reads; what it counts |
| `Reward` | A reward declared |
| `QuestReader` | A quest from plain values |
| `Catalog`, `WritableCatalog`, `InMemoryCatalog`, `Chains` | The quests of an application; their chains checked |
| `Contribution`, `Enrolment`, `Completion` | What an activity brought; a member's decision; a quest accomplished |
| `QuestState`, `QuestStatus` | Where a quest stands for a member |
| `Window` | A stretch of time |
| `Erasure` | What erasing a member removed |
| `Names` | The codes of quests, steps and reward kinds |
| `Schedule\Schedule`, `Schedule\Occurrence` | When a quest is open; one time it is |
| `Schedule\Permanent`, `Schedule\Dated` | Open always; open between two dates |
| `Schedule\ScheduleReader` | A schedule of the application's own, read from a definition |
| `Progress\ProgressCalculator`, `Progress\QuestProgress`, `Progress\StepProgress`, `Progress\Finish` | Reading progress; a quest's; a step's; when it was accomplished |
| `Duel\Duels`, `Duel\Duel`, `Duel\DuelStatus`, `Duel\DuelStanding` | Challenges between members; a duel; where it stands; how far each side is |
| `Store\ContributionStore`, `Store\InMemoryContributionStore` | Where the contributions are kept |
| `Store\EnrolmentStore`, `Store\InMemoryEnrolmentStore` | Where the enrolments are kept |
| `Store\CompletionStore`, `Store\InMemoryCompletionStore` | Where the journal of accomplished quests is kept |
| `Store\DuelStore`, `Store\InMemoryDuelStore` | Where the duels are kept |
| `Exception\QuestsException` and its four exceptions | [Exceptions](exceptions.md) |
