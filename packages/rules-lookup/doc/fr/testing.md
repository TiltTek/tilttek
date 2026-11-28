<!-- phil-dev source-sha256=ad6ad23fa119d208edc880f1bdd2a821f31919313e18421d243f44c279f8d058 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : la normalisation — accents précomposés et décomposés, formes de compatibilité, casse au-delà de
l'ASCII, hangeul recomposé, marques à chasse gardées, texte qui n'est pas de l'UTF-8 —, des recherches à requêtes
accentuées et en casse variée, les clés et les locales, les
identifiants, les règles et leurs refus, les requêtes et leurs bornes, le dépôt en mémoire — le score, le début d'un
mot, la locale du lecteur, les filtres, les pages, les égalités, les renvois, le remplacement de règles —, les règles
d'un système, la recherche avec l'attribution de chaque résultat, et le message de réindexation. Dans le monodépôt,
Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/query.php` est la cible de fuzzing des requêtes : tout texte est refusé avec les exceptions de la
bibliothèque, ou donne des mots qui se normalisent en eux-mêmes et une page dans ses bornes. `composer fuzz` l'exécute.

## Bancs d'essai

`tests/Benchmark/SearchBench.php` mesure un dépôt de 5 000 règles en anglais et en français, chaque texte de 60 mots
d'un vocabulaire de 300. Sur PHP 8.5 avec `intl` et sans cache d'opcodes : la construction du dépôt 0,23 s, une recherche de deux mots
0,14 ms, une recherche qui se termine par le début d'un mot 3,7 ms, la normalisation d'une requête accentuée 4 µs, le
listage d'une catégorie 2,1 ms.

## Tester du code qui cherche des règles

Construisez quelques règles, ou les règles d'un petit système, et un dépôt en mémoire :

```php
$source = new Provenance('Test', Licence::Owned);
$lookup = new RulesLookup(new InMemoryRuleStore(
    new Rule(new RuleId('test', 'condition.blinded'), Translations::of(['en' => 'Blinded']), $source),
));
```
