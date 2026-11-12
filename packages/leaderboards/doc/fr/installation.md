<!-- phil-dev source-sha256=8044e197a4a4f4d81cddcfa8c00bd22f08e343651dadb7a13bd657c44a2c037e -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent.
- `tilttek/activity`, le journal de l'activité des membres que lisent les classements.
- `phil/resource`, la référence opaque d'un membre, et `psr/clock`, l'horloge d'où se lit la période en cours.

## Installer

```sh
composer require tilttek/leaderboards
```

Une application Symfony installe plutôt `tilttek/leaderboards-bundle`, qui apporte la bibliothèque, ses stockages
Doctrine DBAL, la configuration des classements, les commandes et les gabarits.

## Assembler la bibliothèque à la main

Les classements, les trois stockages et une horloge forment `Leaderboards` ; les classements et le stockage des scores
forment la projection, que les projections du journal tiennent à jour :

```php
$boards = Boards::fromArray($configuration, 'America/Toronto');
$scores = new InMemoryScoreStore();
$projection = new LeaderboardProjection($boards, $scores);
$leaderboards = new Leaderboards($boards, $scores, new InMemorySnapshotStore(), new InMemoryAppearanceStore(), $clock, $observer);

$journal = new Journal($log, $clock, observers: [new LiveProjections($projector, new Projections([$projection]))]);
```

Les stockages en mémoire oublient tout avec le processus : une application écrit les siens sur son propre support
([Écrire un stockage](writing-a-store.md)), ou prend ceux du bundle.
