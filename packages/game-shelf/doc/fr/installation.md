<!-- phil-dev source-sha256=31f6d71ce21002f6dce8f093e6ca72d8734d5f33d8cb5fc0ed0f03fb096ef03a -->
# Installation

```sh
composer require tilttek/game-shelf
```

Le paquet exige PHP 8.5 ou une version plus récente, `ext-mbstring` et `tilttek/game-catalog`, dont il vérifie les jeux
auprès du `Catalog`.

Une application donne au service :

- un `Contract\ShelfStore` qui garde les étagères dans sa base de données ;
- le `Catalog` de `tilttek/game-catalog` ;
- un `Contract\ShelfObserver`, pour envoyer ses messages, ou rien.

`Store\InMemoryShelfStore` garde les étagères en mémoire, pour les tests.
