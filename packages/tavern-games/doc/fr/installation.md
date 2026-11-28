<!-- phil-dev source-sha256=e083502ab27b19d0c3db8002eeacb5ea8a9343e9e4809f47a3b604931648fa20 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-sodium`.
- `tilttek/game-engine` 0.1, qui fait tourner les tables, garde leurs journaux et tire leur hasard.
- `tilttek/dice` 0.3, qui lance les dés des jeux de dés.
- `tilttek/card-deck` 0.1, qui garde les cartes des jeux de cartes.

## Installation

```sh
composer require tilttek/tavern-games
```

## Câbler la bibliothèque

La bibliothèque n'a aucun service à configurer. Une application énumère ses jeux de taverne, donne les mêmes jeux au
`Tables` du moteur, et donne à `TavernTables` son minuteur de tour quand ses tours ont un délai :

```php
$games = new TavernGames(new Pig(), new ShipCaptainCrew(), new CrazyEights());
$tables = new Tables($games->games(), $logStore, $stateCache, $publisher);
$tavern = new TavernTables($tables, $games, new SchedulerTurnTimer($scheduler)); // the application's adapter
```

`TavernTables` et les jeux ne gardent rien entre deux appels ; le `Tables` qu'il reçoit garde les états qu'il a lus, et
son `reset()` les libère entre deux requêtes d'un *worker*. Le magasin des journaux, le cache d'états et l'éditeur sont
ceux de `tilttek/game-engine`.

`Store\InMemoryTurnTimer` sert aux tests : il garde les tours en cours dans l'objet, qu'un *worker* ne doit jamais
partager entre des requêtes.
