<!-- phil-dev source-sha256=6339a5b3ed1a11269c14e6be0d2764c4edb66fc6265d1355d44685a9d286427a -->
# Mises à jour en direct

Les écrans des joueurs suivent un jeu à mesure qu'il se joue : les cases que l'animateur appelle, les cartons qui
gagnent et, sur le carton d'un congrès, les cases qu'un geste coche. `Mercure\BingoPublisher` les pousse par Mercure,
sous forme de mises à jour privées qui nomment le jeu, le numéro de série du carton, la case et le motif, jamais un
joueur : chaque écran qui reçoit une mise à jour lit auprès de l'application ce qu'il peut montrer, avec ses propres
droits. C'est facultatif, et cela exige `symfony/mercure` :

```sh
composer require symfony/mercure
```

## Brancher le publicateur

```php
use Tilttek\Bingo\Mercure\BingoPublisher;

$publisher = new BingoPublisher(
    $hub,                                   // Symfony\Component\Mercure\HubInterface
    'https://example.com/bingo/games/',     // the topic prefix; the game's identifier follows it
);
```

| Méthode | Événement | Sujet | Données de la mise à jour |
|---|---|---|---|
| `squareCalled()` | `SquareCalled` | Celui du jeu | `{"type": "square-called", "game": …, "square": …}` |
| `bingoWon()` | `BingoWon` | Celui du jeu | `{"type": "bingo-won", "game": …, "serial": …, "pattern": …}` |
| `squareTicked()` | `SquareTicked` | Celui du carton | `{"type": "square-ticked", "game": …, "serial": …, "square": …}` |

Dans une application Symfony, enregistrez-les sur le répartiteur d'événements :

```yaml
# config/services.yaml
Tilttek\Bingo\Mercure\BingoPublisher:
    arguments: ['@mercure.hub.default', '%env(BINGO_TOPIC_PREFIX)%']
    tags:
        - { name: kernel.event_listener, event: Tilttek\Bingo\Event\SquareCalled, method: squareCalled }
        - { name: kernel.event_listener, event: Tilttek\Bingo\Event\BingoWon, method: bingoWon }
        - { name: kernel.event_listener, event: Tilttek\Bingo\Event\SquareTicked, method: squareTicked }
```

## S'abonner

`topic($game)` donne le sujet d'un jeu, le préfixe suivi de son identifiant encodé pour une URL, et
`cardTopic($game, $serial)` le sujet d'un seul carton, le sujet du jeu suivi de `/` et du numéro de série. Les mises à
jour sont privées : l'application donne à chaque joueur un jeton d'abonné pour le sujet du jeu et pour celui de son
propre carton seulement, une fois qu'elle a vérifié que le carton est le sien. Un écran qui reçoit une mise à jour lit
de nouveau le carton auprès de l'application ; il ne coche jamais une cellule de lui-même.

Le publicateur ne garde rien entre deux appels : une seule instance sert toutes les requêtes d'un *worker*.
