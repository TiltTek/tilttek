# Extension points

| To | Do |
|---|---|
| Keep the progress elsewhere | Alias `ContributionStore`, `EnrolmentStore`, `CompletionStore` or `DuelStore` to your own service |
| Keep the quests elsewhere | Alias `Catalog` — and `WritableCatalog` if it is edited — to your own catalog; check its chains with `Chains::check()` |
| Open quests at dates of your own | Write a `ScheduleReader` and decorate the `QuestReader` service with it in its list of readers |
| Let members join team quests | Alias `Tilttek\Quests\Contract\TeamMembership` to your own service, answering from your records of the teams; until then the bundle's `NoTeamMembership` refuses every team quest |
| Act on a quest accomplished | Listen to `QuestCompleted`, `DuelWon` and `DuelExpired` |
| Replace what is told | Replace `DispatchingObserver` by your own `QuestObserver` |
| Show the quests otherwise | Write your own templates over the Twig functions, or read `Quests` and `Duels` in your controllers |
