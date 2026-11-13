<!-- phil-dev source-sha256=22bd8c3bf54618b42804a1288ca41f48dd8e89da048f07897b4a038a1b34d6af -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension ne sont stables qu'à partir de sa version 1.0. D'ici là, une version
mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Où |
|---|---|---|
| `Tilttek\GameEngine\Contract\Game` | Un jeu : tout service qui l'implémente, étiqueté `tilttek_game_engine.game` par autoconfiguration | [Installation](installation.md#les-jeux) |
| `Application\Contract\ViewerResolver` | Qui lit une table par les points de terminaison : un siège et ses préférences, le public, ou personne ; `view.resolver` | [Tables en direct](live-tables.md#le-lecteur) |
| `Application\Contract\TableLock` | Un autre verrou des tables ; l'alias de celui du bundle quand `lock.factory` est défini | [Stockage](storage.md#le-verrou) |
| `Tilttek\GameEngine\Contract\LogStore` | Un autre magasin de journaux, en créant un alias de l'interface | [Stockage](storage.md#les-journaux) |
| `Tilttek\GameEngine\Contract\StateCache` | Un autre cache d'états, en créant un alias de l'interface | [Stockage](storage.md#le-cache-détats) |
| `Tilttek\GameEngine\Contract\TablePublisher` | Une autre façon d'informer les joueurs, en créant un alias de l'interface | [Tables en direct](live-tables.md#la-publication) |
| L'événement `game-table:view` | Le rendu de la vue d'un jeu dans la page | [Tables en direct](live-tables.md#le-gabarit-et-le-contrôleur-stimulus) |

Aucun service du bundle ne garde d'état entre deux requêtes ; le magasin en mémoire, pour les tests, est réinitialisé
entre deux requêtes d'un *worker*.
