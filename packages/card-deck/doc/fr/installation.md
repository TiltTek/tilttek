<!-- phil-dev source-sha256=6f54ebed9c6fc4e2d36b61c9142f8274b0ff9a34fbdb336ab54a8e3e34cbc62b -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `tilttek/dice` 0.3, qui donne la graine, son engagement et son générateur aléatoire.

## Installer

```sh
composer require tilttek/card-deck
```

## Brancher la bibliothèque

La bibliothèque n'a aucun service à configurer. Une application donne à `CardTable` son propre magasin — une
implémentation de `Contract\GameStore` sur sa base de données
([Parties et magasins](games-and-stores.md#un-magasin-à-vous)) :

```php
$table = new CardTable(new DoctrineGameStore($connection)); // the application's adapter
```

`CardTable` ne garde rien entre deux appels : une instance sert chaque requête, dans un *worker* comme dans une
requête. Chaque appel lit la partie dans le magasin et rejoue son journal ; un journal de 1 000 gestes se rejoue en
quelques millisecondes ([Tests](testing.md#bancs-dessai)).

`Store\InMemoryGameStore` sert aux tests : il garde ses parties dans l'objet, qu'un *worker* ne doit jamais partager
entre des requêtes.
