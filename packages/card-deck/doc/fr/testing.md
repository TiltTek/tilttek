<!-- phil-dev source-sha256=b340b54ec1f891ff8c91851b1167ed9a48c3b363801217376b3a1f313115b5f5 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : les noms et les identifiants, les cartes, les paquets et
le paquet standard, les zones, chaque geste et ses refus, le codec, le mélange fixé à une graine, l'état, sa relecture
et son empreinte, les vues, la table avec ses versions, le magasin en mémoire, et la partie publiée. Des tests de
propriétés jouent des parties au hasard à partir de graines fixes et vérifient, après chaque geste, qu'aucune carte
n'est perdue ni dupliquée, qu'un geste refusé ne change rien, qu'aucune vue d'aucun joueur ne contient une carte cachée,
et que le journal se rejoue vers le même état. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des
mutants couverts.

`tests/Fuzz/gesture.php` est la cible de fuzzing du codec de gestes : tout JSON est refusé avec les exceptions de la
bibliothèque, ou donne un geste qui s'écrit de nouveau vers le même tableau. `composer fuzz` l'exécute.

## Bancs d'essai

`tests/Benchmark/ReplayBench.php` rejoue un journal sur un paquet de 54 cartes et construit la vue d'un joueur, comme
le fait chaque requête d'une partie. Sur PHP 8.6 sans cache d'opcodes : 1 000 gestes en environ 4 ms, 10 000 gestes — la
borne d'une partie — en environ 30 ms.

## Tester du code qui joue des cartes

Donnez à `CardTable` le magasin en mémoire et une graine fixe : les mélanges sont alors les mêmes à chaque exécution.

```php
$table = new CardTable(new InMemoryGameStore());
$table->open($game, StandardDeck::create(), Seed::fromHex(str_repeat('ab', 32)));
$table->start($game);
```
