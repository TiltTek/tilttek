<!-- phil-dev source-sha256=49c86c818bfc567d5718003aa7f0b513c4b3be6d435a064b4b0ac319c8a77b72 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : les formes des codes, des identifiants
et des noms, chaque borne d'une carte, d'un format, d'une liste tabou et d'un paquet, les listes tabou dans l'ordre et
selon le fuseau horaire propre du jour, les règles d'un jour, les formats et les paquets écrits puis relus avec tout le
reste refusé, chaque violation et son message, les collections, les versions enregistrées, conservées, en conflit et
copiées, et les différences. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts. Les
cartes et les formats des tests sont inventés.

`tests/Fuzz/deck-format.php` et `tests/Fuzz/deck.php` sont les cibles de fuzzing d'un format et d'un paquet : tout
texte se lit comme un format ou un paquet qui s'écrit de nouveau à l'identique, ou est refusé avec `InvalidDeck`, jamais
avec une autre exception. `composer fuzz` les exécute.

`tests/Benchmark/DeckBuilderBench.php` mesure le plus grand paquet — 1 000 cartes distinctes — validé selon un format de
10 000 cartes bannies et 10 000 cartes restreintes et de 100 listes tabou avec une collection, ses différences avec une
autre version, et sa lecture depuis du JSON.

## Tester une application

Donnez au validateur des catalogues en mémoire de cartes inventées, et à `Decks` l'entrepôt en mémoire :

```php
$validator = new DeckValidator(new InMemoryCardCatalog(new Card('fox', 'Ember Fox')), new InMemoryFormatCatalog($format));
$decks = new Decks(new InMemoryDeckStore());
```
