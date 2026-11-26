<!-- phil-dev source-sha256=081f937d8085ef003149bf5b1a43c0580db4bbcad838616aeb2690ca62259252 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : les entrées et leurs refus, le lecteur de lignes, le
catalogue — une année, une période, un jour lu dans le fuseau horaire du catalogue, les filtres, les entrées propres à
une application, les identifiants donnés deux fois, la plage des années —, les jours d'invites — leur indice, à
travers un changement d'heure et dans un autre fuseau horaire —, et les données fournies. Le test des données vérifie
chaque ligne avec `DataCheck`, calcule chaque entrée une fois par année de 2023 à 2030 et en 2100, années bissextiles
comprises, fixe des dates connues — RPGaDAY du 1er au 31 août — et montre qu'un cinquième samedi de février ne donne
une date que dans une année qui en a un. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## Bancs d'essai

`tests/Benchmark/CatalogBench.php` mesure la construction du catalogue fourni, les dates d'un jour et celles d'une
année. Sous PHP 8.6 sans cache d'opcodes : construction 0,05 ms, un jour 0,03 ms, une année 0,06 ms.

## Tester le code qui montre des événements

Construisez un catalogue de quelques événements dans un fuseau horaire fixe et demandez un jour connu :

```php
$catalog = new Catalog([
    new Entry('prompt-month', ['en' => 'Prompt month', 'fr' => 'Mois d’invites'], RuleParser::parse('08-01'),
        'https://example.org/prompts', 'players', new DateTimeImmutable('2026-11-21'), days: 31, prompts: true),
], new DateTimeZone('UTC'));

$catalog->promptDays(new DateTimeImmutable('2026-08-12'))[0]->index; // 12
```
