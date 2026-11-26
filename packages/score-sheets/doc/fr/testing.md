<!-- phil-dev source-sha256=97110634da89b5dc280550cd165d2a861cb6f1f2acbdd04991e5bb8bf6b80fcb -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : les déclarations lues depuis JSON et leurs refus, chaque
opération et son codec, les saisies et les équipes, le tableau des scores avec ses explications, ses rangs, ses bris
d'égalité et ses équipes, la vie d'une feuille de partie avec ses versions et ses révisions, le résultat et son
message, et la feuille vierge. Des tests de propriétés saisissent des valeurs au hasard à partir de graines fixes et
vérifient, après chaque saisie, que le total est la somme des catégories qui comptent, que les rangs suivent les
totaux, et qu'une équipe contient la somme de ses membres.

`tests/Fuzz/sheet.php` est la cible de fuzzing des déclarations : tout texte est refusé avec les exceptions de la
bibliothèque, ou donne une déclaration qui s'écrit de nouveau vers la même, une feuille vierge et un tableau des
scores. `composer fuzz` l'exécute.

## Tester du code qui tient des scores

Donnez à `ScoreKeeper` le magasin en mémoire et une feuille déclarée dans le test :

```php
$keeper = new ScoreKeeper(new InMemoryMatchSheetStore());
$keeper->prepare($match, SheetDefinition::fromJson($json), new Entries([$ada, $bo]));
```
