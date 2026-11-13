<!-- phil-dev source-sha256=756aad4acdf0d61a665c232e71a4be7696a668a2bdf85d110e07fafa7a196521 -->
# Configuration

La configuration du bundle se trouve sous `tilttek_game_engine` ; chaque option a une valeur par défaut :

```yaml
# config/packages/tilttek_game_engine.yaml
tilttek_game_engine:
    store: doctrine
    connection: default
    cache:
        pool: cache.app
        lifetime: ~
    lock:
        factory: lock.factory
        ttl: 30
    mercure:
        enabled: true
        hub: Symfony\Component\Mercure\HubInterface
        topic: 'urn:tilttek-game-engine:table:{table}'
        private: true
    view:
        resolver: App\Game\SeatOfTheMember
        audience: false
    roleplaying_scene: false
    translation_domain: tilttek_game_engine
    kit_subdirectory: ~
```

| Option | Valeur par défaut | Ce qu'elle fait |
|---|---|---|
| `store` | `doctrine` | Où les journaux sont conservés : `doctrine` sur la connexion ci-dessous, ou `memory` pour les tests, réinitialisé entre deux requêtes d'un *worker*. |
| `connection` | `default` | La connexion Doctrine DBAL des journaux. |
| `cache.pool` | `cache.app` | Le pool PSR-6 où les états sont mis en cache, partagé par tous les processus ; `null` rejoue le journal à chaque lecture. |
| `cache.lifetime` | `null` | Les secondes pendant lesquelles un état est gardé après sa mise en cache ; aussi longtemps que le pool le garde quand c'est `null`. |
| `lock.factory` | `null` | Le service d'une fabrique de verrous de Symfony qui sépare deux coups à une même table, et deux écritures du cache d'états d'une même table ; aucun par défaut. |
| `lock.ttl` | `30` | Les secondes pendant lesquelles un verrou est gardé quand le processus qui le détient meurt, 1 ou plus. |
| `mercure.enabled` | `false` | Publie chaque changement sur le sujet Mercure de sa table. |
| `mercure.hub` | `Symfony\Component\Mercure\HubInterface` | Le service du concentrateur. |
| `mercure.topic` | `urn:tilttek-game-engine:table:{table}` | Le sujet de chaque table, `{table}` tenant lieu une fois de son identifiant. |
| `mercure.private` | `true` | Publie en privé, aux seuls abonnés que le concentrateur a autorisés pour le sujet. |
| `view.resolver` | `null` | Le service qui implémente `ViewerResolver` et nomme le siège du joueur courant. |
| `view.audience` | `false` | Sans résolveur, laisse n'importe qui lire la vue du public de chaque table ; sinon, personne ne lit une table. |
| `roleplaying_scene` | `false` | Enregistre le jeu prêt à l'emploi de la bibliothèque pour les phases d'une scène de jeu de rôle. |
| `translation_domain` | `tilttek_game_engine` | Le domaine de traduction des textes du gabarit. |
| `kit_subdirectory` | `null` | Le sous-répertoire des composants où les recettes du kit sont installées ; celui du Toolkit de Symfony UX quand c'est `null`. |

Une fabrique de verrous ou un concentrateur nommé alors que son composant manque empêche la construction du conteneur,
avec le paquet à installer.
