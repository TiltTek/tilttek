<!-- phil-dev source-sha256=b179233bcf409d40ae33ed0744191129a11a5c36aa44c8d7645aa6b4d8dee920 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `tilttek/game-engine` 0.1, qui fait tourner les tables, garde leurs journaux et tire leur hasard.

## Installation

```sh
composer require tilttek/mini-games
```

## Câbler la bibliothèque

La bibliothèque n'a aucun service à configurer. Une application énumère ses mini-jeux, donne les mêmes jeux au `Tables`
du moteur, et donne à `DailyGames` un secret d'au moins 32 octets et, quand elle classe ses joueurs, un puits de
scores :

```php
$games = new MiniGames(new BullsAndCows(), new HigherOrLower(), new MyWordGame());
$tables = new Tables($games->games(), $logStore, $stateCache, $publisher);
$daily = new DailyGames($tables, $games, new DailySeed($secret), new LeaderboardScoreSink($activities));
```

Le secret est une valeur de la configuration de l'application, gardée comme tout autre secret : avec lui, n'importe qui
pourrait calculer les jeux des jours à venir. Le changer change chaque jeu à partir de la prochaine table ouverte.

`DailyGames` ne garde rien entre deux appels ; le `Tables` qu'il reçoit garde les états qu'il a lus, et son `reset()`
les libère entre deux requêtes d'un *worker*. Le magasin des journaux, le cache d'états et l'éditeur sont ceux de
`tilttek/game-engine`.

`Store\InMemoryScoreSink` sert aux tests : il garde ses scores dans l'objet, qu'un *worker* ne doit jamais partager
entre des requêtes.
