<!-- phil-dev source-sha256=b7ab19bc41fff0256c05494a2eab001384265f50b4cac5c9f65dd74cbe2358a7 -->
# Installation

```sh
composer require tilttek/play-log
```

Le paquet exige PHP 8.5 ou une version plus récente, `ext-mbstring` et `tilttek/game-catalog`, dont il vérifie les jeux
auprès du `Catalog`.

Une application donne à `PlayLog` :

- un `Contract\PlayStore` qui garde les parties dans sa base de données ;
- le `Catalog` de `tilttek/game-catalog` ;
- un `Contract\PlayObserver`, pour envoyer ses messages, ou rien ;
- un `Contract\PlayerConsent`, quand les membres peuvent accepter d'avance d'être liés aux parties des autres, ou rien.

`PlayStatistics` prend le même `Contract\PlayStore`. `Store\InMemoryPlayStore` garde les parties en mémoire, pour les
tests.
