<!-- phil-dev source-sha256=5e25191100b2196479c69348239aa2097eae25aa2d7dd592bba1b33a04400a8e -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `symfony/workflow`, la machine à états des phases.
- `symfony/options-resolver`, qui vérifie les options d'une table.

## Installer le paquet

```sh
composer require tilttek/game-engine
```

## Câbler la bibliothèque

`Tables` reçoit les jeux qu'une application offre, un magasin de journaux, et facultativement un cache d'états et un
éditeur :

```php
$tables = new Tables(
    new Games(new RoleplayingScene(), new MyCardGame()),
    $logStore,            // Contract\LogStore: the truth of every table
    $stateCache,          // Contract\StateCache: NullStateCache by default, every read replays
    $publisher,           // Contract\TablePublisher: NullTablePublisher by default
);
```

Les magasins en mémoire oublient tout avec le processus ; une application écrit les siens sur son stockage
([Conserver et publier](storing-and-publishing.md)). `Engine` seul fait tourner une table sans aucun magasin, pour un
script ou un test ([Tables, coups et tours](tables-and-moves.md)).

`Engine`, `Tables` et les jeux ne gardent aucun état entre deux appels : une seule instance sert donc toutes les
requêtes d'un *worker* ; `InMemoryStateCache` et `InMemoryLogStore` gardent un état, et sont réservés aux tests et aux
scripts. `Tables` garde les états qu'elle a lus tant que leur journal ne change pas ; un *worker* appelle son `reset()`
entre deux requêtes.
