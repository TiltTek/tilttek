<!-- phil-dev source-sha256=7f479d638eba46855ca536e2947882940eea626c90a42ad1ea95f5db6589062e -->
# Installation

## Prérequis

PHP 8.5 ou une version plus récente. La bibliothèque n'a aucune dépendance.

## Installer

```sh
composer require tilttek/score-sheets
```

## Brancher la bibliothèque

La bibliothèque n'a aucun service à configurer. Une application donne à `ScoreKeeper` son propre magasin — une
implémentation de `Contract\MatchSheetStore` sur sa base de données
([Feuilles de partie](match-sheets.md#un-magasin-à-vous)) :

```php
$keeper = new ScoreKeeper(new DoctrineMatchSheetStore($connection)); // the application's adapter
```

`ScoreKeeper` et `Scoring\Scorer` ne gardent rien entre deux appels : une instance sert chaque requête, dans un
*worker* comme dans une requête. Une déclaration se lit une fois — depuis un fichier JSON que l'application livre, ou
depuis sa base de données — et c'est une valeur qu'elle peut mettre en cache.

`Store\InMemoryMatchSheetStore` sert aux tests : il garde ses feuilles dans l'objet, qu'un *worker* ne doit jamais
partager entre des requêtes.
