<!-- phil-dev source-sha256=1fe0100050543eeed1c818772468ec8d4dc55649af023a69773e72a4c2172230 -->
# Installation

## Prérequis

PHP 8.5 ou une version plus récente. La bibliothèque n'a besoin d'aucun autre paquet.

## Installer

```sh
composer require tilttek/deck-builder
```

## Câbler la bibliothèque

```php
use Tilttek\DeckBuilder\{DeckValidator, Decks};

$validator = new DeckValidator(
    $cards,     // Contract\CardCatalog: the application's cards
    $formats,   // Contract\FormatCatalog: the application's formats
);

$decks = new Decks(
    $store,     // Contract\DeckStore: every version of every deck
);
```

`DeckValidator` et `Decks` ne gardent rien entre deux appels : une seule instance sert toutes les requêtes d'un
*worker*. Les adaptateurs en mémoire — `Catalog\InMemoryCardCatalog`, `Catalog\InMemoryFormatCatalog` et
`Store\InMemoryDeckStore` — conviennent aux cartes et aux formats déclarés dans la configuration de l'application, aux
tests et aux scripts ; un catalogue de milliers de cartes se lit mieux dans la base de données de l'application, carte
par carte, par son propre `Contract\CardCatalog`.

La bibliothèque ne lit aucune horloge : le jour d'une validation et le moment d'une version sont passés à chaque appel,
de sorte qu'un paquet est vérifié selon les règles d'un événement passé ou à venir de la même façon que selon celles
d'aujourd'hui.
