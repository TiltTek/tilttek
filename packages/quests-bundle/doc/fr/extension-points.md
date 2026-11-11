<!-- phil-dev source-sha256=3f473f32177bf0fe440fd78ff4f1bb8dd672f5c8a736ca64cfeaf02ab5951a96 -->
# Points d'extension

| Pour | Faites |
|---|---|
| Garder la progression ailleurs | Faites de `ContributionStore`, `EnrolmentStore`, `CompletionStore` ou `DuelStore` un alias de votre propre service |
| Garder les quêtes ailleurs | Faites de `Catalog`, et de `WritableCatalog` s'il est modifié, un alias de votre propre catalogue ; vérifiez ses chaînes avec `Chains::check()` |
| Ouvrir des quêtes à vos propres dates | Écrivez un `ScheduleReader` et décorez le service `QuestReader` pour l'ajouter à sa liste de lecteurs |
| Agir sur une quête accomplie | Écoutez `QuestCompleted`, `DuelWon` et `DuelExpired` |
| Remplacer ce qui est annoncé | Remplacez `DispatchingObserver` par votre propre `QuestObserver` |
| Afficher les quêtes autrement | Écrivez vos propres gabarits par-dessus les fonctions Twig, ou lisez `Quests` et `Duels` dans vos contrôleurs |
