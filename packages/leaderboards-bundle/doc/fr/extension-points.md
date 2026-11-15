<!-- phil-dev source-sha256=2d0b2f694379d8edfbeedf3bbafff738fd78eac993c60ce7e8d4fe7acbe81b9c -->
# Points d'extension

- **Un stockage.** Donnez pour alias à `Tilttek\Leaderboards\Store\ScoreStore`, `SnapshotStore`, `AppearanceStore` ou
  `LeagueStore` votre propre service ; il doit réussir le cas de test du contrat de la bibliothèque. Les scores dans
  Valkey n'exigent aucun code : activez `valkey`. Avec `celebrate_on: score_change`, `LeadWatch` enveloppe le stockage de
  scores que la configuration a choisi ; pour surveiller le vôtre, décorez `Tilttek\Leaderboards\Store\ScoreStore` avec
  `LeadWatch` de la même façon.
- **Les premières places.** Donnez pour alias à `Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore` votre
  propre service ; il doit garder une première place une seule fois par classement, par période et par membre, même
  quand deux processus la réclament au même moment.
- **Le client Valkey.** Nommez un client que l'application a déjà dans `valkey.client`, ou remplacez
  `Tilttek\Leaderboards\Store\Valkey\RawCommandConnection` par une `ValkeyConnection` de votre cru.
- **Les écrans.** Remplacez `@TilttekLeaderboards/league.html.twig` ou `teams.html.twig` sous
  `templates/bundles/TilttekLeaderboardsBundle/`, ou installez d'autres recettes des mêmes noms.
- **Les classements.** Remplacez le service `Tilttek\Leaderboards\Boards`, par exemple à partir d'une base de données,
  et reconstruisez la projection quand ils changent.
- **L'observateur.** `Leaderboards` et `Leagues` informent `DispatchingObserver` ; écoutez ses événements, ou
  décorez-le.
- **Les valeurs rapportées.** Un écouteur de l'événement du paquet qui garde un niveau ou un solde appelle
  `Leaderboards::report()`.
