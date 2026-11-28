<!-- phil-dev source-sha256=8ee50c65f7f295880d9bdc37d2a180faf3e58cf68ba1844d894f73b62cf21eb3 -->
# Installation

```sh
composer require tilttek/game-night
```

Le paquet exige PHP 8.5 ou une version plus récente, `psr/clock` et `tilttek/game-catalog`, dont il lit les jeux dans
le `Catalog`.

Une application fournit les services :

- un `Contract\NightStore` qui conserve les soirées dans sa base de données ;
- une horloge PSR-20, comme celle de `symfony/clock` ;
- le `Catalog` de `tilttek/game-catalog` ;
- un `Contract\NightObserver`, pour envoyer ses messages et ses notifications ;
- un `Contract\ShelfReader`, pour lire les jeux que les membres peuvent apporter ;
- un `Contract\PlayJournal`, pour recevoir les jeux joués.

`Store\InMemoryNightStore` conserve les soirées en mémoire, pour les tests.
