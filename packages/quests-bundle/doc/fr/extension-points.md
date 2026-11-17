<!-- phil-dev source-sha256=b0dd5b7c1a6a369630f77aecde67961896614b88229ef325eb4aa54157ecd521 -->
# Points d'extension

| Pour | Faites |
|---|---|
| Garder la progression ailleurs | Faites de `ContributionStore`, `EnrolmentStore`, `CompletionStore` ou `DuelStore` un alias de votre propre service |
| Garder les quêtes ailleurs | Faites de `Catalog`, et de `WritableCatalog` s'il est modifié, un alias de votre propre catalogue ; vérifiez ses chaînes avec `Chains::check()` |
| Ouvrir des quêtes à vos propres dates | Écrivez un `ScheduleReader` et décorez le service `QuestReader` pour l'ajouter à sa liste de lecteurs |
| Laisser les membres rejoindre des quêtes d'équipe | Faites de `Tilttek\Quests\Contract\TeamMembership` un alias de votre propre service, qui répond à partir de vos dossiers d'équipes ; d'ici là, le `NoTeamMembership` du bundle refuse toute quête d'équipe |
| Agir sur une quête accomplie | Écoutez `QuestCompleted`, `DuelWon` et `DuelExpired` |
| Remplacer ce qui est annoncé | Remplacez `DispatchingObserver` par votre propre `QuestObserver` |
| Afficher les quêtes autrement | Écrivez vos propres gabarits par-dessus les fonctions Twig, ou lisez `Quests` et `Duels` dans vos contrôleurs |
