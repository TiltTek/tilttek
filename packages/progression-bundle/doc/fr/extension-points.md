<!-- phil-dev source-sha256=e289b2bc089818c9453e4a378f7c868107e395ac632f4765af3278b30bc5fd33 -->
# Points d'extension

- **Un magasin.** Faites pointer l'alias `Tilttek\Progression\Store\ExperienceStore`, `MilestoneStore` ou `StreakStore`
  vers votre propre service ; il doit réussir le cas de test de contrat de la bibliothèque.
- **Le fuseau horaire d'un membre.** `timezone_context` le lit dans le contexte de l'activité. Pour le lire ailleurs
  (jamais dans quelque chose qui change après le geste, sinon les reconstructions donnent d'autres jours), définissez le
  service `Tilttek\Progression\Streak\TimezoneResolver`.
- **L'observateur.** La projection et le gardien avertissent `DispatchingObserver` ; écoutez ses événements, ou
  décorez-le.
- **Les pistes ou les séries.** Remplacez le service `Tilttek\Progression\Tracks` ou
  `Tilttek\Progression\Streak\StreakRules` (à partir d'une base de données, par exemple) et reconstruisez la projection
  quand ils changent.
