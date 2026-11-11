# Extension points

| To | Do |
|---|---|
| Keep the progress elsewhere | Alias `ContributionStore`, `EnrolmentStore`, `CompletionStore` or `DuelStore` to your own service |
| Keep the quests elsewhere | Alias `Catalog` — and `WritableCatalog` if it is edited — to your own catalog; check its chains with `Chains::check()` |
| Open quests at dates of your own | Write a `ScheduleReader` and decorate the `QuestReader` service with it in its list of readers |
| Act on a quest accomplished | Listen to `QuestCompleted`, `DuelWon` and `DuelExpired` |
| Replace what is told | Replace `DispatchingObserver` by your own `QuestObserver` |
| Show the quests otherwise | Write your own templates over the Twig functions, or read `Quests` and `Duels` in your controllers |
