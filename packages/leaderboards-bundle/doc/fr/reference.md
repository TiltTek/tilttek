<!-- phil-dev source-sha256=61765aa76185badeea70b00e38ce7b2aa1bee7332db2ea5acbc81ae7b197c91d -->
# Référence

| Classe ou service | Rôle |
|---|---|
| `TilttekLeaderboardsBundle` | Le bundle et sa configuration `tilttek_leaderboards` |
| `Infrastructure\DependencyInjection\LeaderboardsServices` | Les services qu'assemble la configuration |
| `Infrastructure\Doctrine\LeaderboardsTables` | Les neuf tables |
| `Infrastructure\Doctrine\LeaderboardsSchemaListener` | Les tables dans le schéma que génère l'ORM |
| `Infrastructure\Doctrine\DbalScoreStore` | Les contributions et les scores sur DBAL |
| `Infrastructure\Doctrine\DbalSnapshotStore` | Les instantanés et leurs lignes sur DBAL |
| `Infrastructure\Doctrine\DbalAppearanceStore` | Les choix des membres sur DBAL |
| `Infrastructure\Doctrine\DbalLeagueStore` | Les paliers, les appartenances et les semaines réglées sur DBAL |
| `Infrastructure\Doctrine\DbalLeadStore` | Les premières places prises sur DBAL |
| `Infrastructure\Doctrine\Rows` | L'insertion écrite une seule fois et la lecture d'une ligne |
| `Infrastructure\Event\DispatchingObserver`, `SnapshotTaken`, `LeagueSettled`, `TopReached` | Un instantané, une semaine réglée et un membre qui a atteint le sommet sous forme d'événements |
| `Infrastructure\Event\Summit` | Comment un membre a atteint le sommet : première place ou palier le plus haut, les règles que partagent l'événement et l'écran |
| `Infrastructure\Twig\LeaderboardsExtension` | Les fonctions Twig |
| `Infrastructure\Twig\LeagueView` | Ce que montre l'écran de ligue d'un membre |
| `Infrastructure\Twig\CelebrationView` | Ce que dit la célébration d'un membre |
| `Infrastructure\Twig\KitComponents`, `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Les noms des recettes du kit, d'après le sous-répertoire du Toolkit |
| `Infrastructure\Console\SnapshotCommand`, `SettleCommand`, `SlideCommand`, `ShowCommand` | Les commandes |
| `Infrastructure\Scheduler\LeaderboardsSchedule` (étiquetée `scheduler.schedule_provider`) | Le calendrier `tilttek_leaderboards` |
| `Infrastructure\Scheduler\TakeDue`, `TakeDueHandler` (public, étiqueté `messenger.message_handler`) | Le message planifié et ce qui le traite |
| `Infrastructure\Privacy\LeaderboardsPrivacy` | L'exportation et l'effacement d'un membre |
| `Tilttek\Leaderboards\Leaderboards` (public) | La façade de la bibliothèque |
| `Tilttek\Leaderboards\LeaderboardProjection` (public, étiqueté `tilttek_activity.projection`) | La projection |
| `Tilttek\Leaderboards\League\Leagues` (public) | Les ligues : divisions, règlements, paliers |
| `Tilttek\Leaderboards\Boards` (public) | Les classements de la configuration |
| `Tilttek\Leaderboards\Store\ScoreStore`, `SnapshotStore`, `AppearanceStore`, `LeagueStore` (alias publics) | Les stockages |
| `Tilttek\Leaderboards\Store\Valkey\ValkeyScoreStore`, `RawCommandConnection`, `tilttek_leaderboards.valkey_client` | Les scores dans Valkey, quand il est activé |
| `templates/standing.html.twig` | Un classement ou un instantané tel qu'un membre le voit |
| `templates/division.html.twig` | La division d'un membre, avec la destination de chaque ligne |
| `templates/league.html.twig` | L'écran de ligue d'un membre, construit des recettes du kit |
| `templates/teams.html.twig` | Un classement d'équipes, construit des recettes du kit |
| `templates/celebration.html.twig` | La célébration d'un membre, le bloc `tilttek_leaderboards_celebration` sur la recette `Celebration` du kit |
