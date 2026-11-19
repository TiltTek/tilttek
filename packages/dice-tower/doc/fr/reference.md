<!-- phil-dev source-sha256=df5ca7b3fc50b6b9f472cdeabab476e2f0274c43d6c7104305dc46b29e8c0937 -->
# Référence

| Type | Rôle |
|---|---|
| `TilttekDiceTowerBundle` | Le bundle : sa configuration et ses services |
| `Domain\Model\TowerDrop` | Un jet déposé dans la tour d'une table : sa position, la table, le jet, le lanceur et le moment |
| `Domain\Contract\TowerDropRepository` | Le journal des tours |
| `Application\DiceTower` | Jets déposés, entrées lues dans l'ordre, entrées manquées relues, dépôts d'un lanceur effacés |
| `Application\TowerEntry` | Une entrée telle que tous les joueurs de la table la voient, et sa charge utile |
| `Application\RollSummary` | Le résumé borné d'un jet enregistré |
| `Application\TowerTopics` | Le sujet de la tour de chaque table |
| `Application\TowerRelays` | Remet chaque entrée aux relais, immédiatement ou par un bus |
| `Application\Message\RelayDrop`, `Application\RelayDropHandler` | Le message d'un relais, avec la table et la position, et son *handler* |
| `Application\Contract\TableAccess` | Si le spectateur courant peut regarder une table |
| `Application\Contract\TowerPublisher` | Pousse chaque nouvelle entrée aux spectateurs |
| `Application\Contract\TowerRelay` | Porte chaque nouvelle entrée vers un autre canal |
| `Application\NoTableAccess`, `Application\NoTowerPublisher` | Personne ne peut regarder ; rien n'est poussé |
| `Infrastructure\Doctrine\DoctrineTowerDropRepository` | Le journal dans Doctrine |
| `Infrastructure\Memory\InMemoryTowerDropRepository` | Le journal en mémoire, pour les tests |
| `Infrastructure\Mercure\MercureTowerPublisher` | Pousse chaque entrée sur le sujet privé de sa table |
| `Infrastructure\Mercure\TowerSubscriptions` | L'abonnement d'un spectateur autorisé, et son témoin d'autorisation |
| `Infrastructure\Http\TowerEntriesController` | La route des entrées manquées |
| `Infrastructure\Twig\DiceTowerExtension` | `dice_tower()`, `dice_tower_topic()` et `dice_tower_kit()` |
| `Infrastructure\Twig\TowerView` | Ce que la surcouche montre d'une entrée |
| `Infrastructure\Twig\KitComponents` | Les noms des composants du kit UX, et les recettes dont la surcouche a besoin |
| `Infrastructure\DependencyInjection\TowerServices` | Enregistre les services |
| `Infrastructure\DependencyInjection\KitSubdirectoryPass` | Nomme les composants du kit d'après le sous-répertoire du Toolkit, sauf si `kit_subdirectory` le dit |

| Service | Public |
|---|---|
| `Tilttek\DiceTower\Domain\Contract\TowerDropRepository` | oui, un alias du journal du stockage |
| `Tilttek\DiceTower\Application\DiceTower` | oui |
| `Tilttek\DiceTower\Application\TowerTopics` | oui |
| `Tilttek\DiceTower\Application\Contract\TableAccess` | non, un alias de `table_access` ou de `NoTableAccess` |
| `Tilttek\DiceTower\Application\Contract\TowerPublisher` | non, un alias de `MercureTowerPublisher` |
| `Tilttek\DiceTower\Application\TowerRelays` | non |
| `Tilttek\DiceTower\Application\RelayDropHandler` | non, avec `message_bus` |
| `Tilttek\DiceTower\Infrastructure\Mercure\TowerSubscriptions` | oui |
| `Tilttek\DiceTower\Infrastructure\Http\TowerEntriesController` | oui |
| `Tilttek\DiceTower\Infrastructure\Twig\DiceTowerExtension` | oui, avec Twig |

| Route | Chemin |
|---|---|
| `tilttek_dice_tower_entries` | `GET /dice-tower/{table}/entries?after={position}` |

| Ressources | Ce qu'elles sont |
|---|---|
| `assets/src/tower_controller.js` | Le contrôleur `tilttek--dice-tower--tower` |
| `assets/src/lib/entries.js` | L'ordre, les mots et l'adresse des entrées |
