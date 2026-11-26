<!-- phil-dev source-sha256=ed369ad882315bc24e390e02b37b0afaa9c17554f8e2a239ecfdd5549b8db8f3 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : les entrées et leurs refus, le lecteur de lignes, le
catalogue — une année, une période, un jour lu dans le fuseau horaire du catalogue, les filtres, les entrées propres à
une application, les identifiants donnés deux fois, la plage des années —, et les données fournies. Le test des
données vérifie chaque ligne avec `DataCheck`, calcule chaque entrée une fois par année de 2023 à 2030 et en 2100,
années bissextiles comprises, fixe des dates connues — `day 256` le 12 septembre d'une année bissextile — et montre
qu'un cinquième samedi de février ne donne une date que dans une année qui en a un. Dans le monodépôt, Infection
maintient le paquet à 100 % des mutants couverts.

## Bancs d'essai

`tests/Benchmark/CatalogBench.php` mesure la construction du catalogue fourni, les dates d'un jour et celles d'une
année. Sous PHP 8.6 sans cache d'opcodes : construction 0,17 ms, un jour 0,15 ms, une année 0,34 ms.

## Tester le code qui montre des dates

Construisez un catalogue de quelques entrées dans un fuseau horaire fixe et demandez un jour connu :

```php
$catalog = new Catalog([
    new Entry('towel-day', ['en' => 'Towel Day', 'fr' => 'Journée de la serviette'], RuleParser::parse('05-25'),
        'https://example.org/towel', 'readers', new DateTimeImmutable('2026-11-21')),
], new DateTimeZone('UTC'));

$catalog->on(new DateTimeImmutable('2026-05-25')); // one Occurrence
```
