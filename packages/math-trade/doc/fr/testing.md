<!-- phil-dev source-sha256=2d5818c4c5ba18965f9be3ea52ca875d860b4fd3140d1f8b6c68725341badb9e -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau :

- le modèle et ses refus : identifiants à leurs bornes, groupes, offres qui se souhaitent elles-mêmes ou souhaitent une
  offre deux fois, participants sans offre, chaque limite à sa borne et un cran au-delà ;
- le solveur sur des listes choisies : deux offres qui se souhaitent l'une l'autre, une boucle de trois, aucun échange
  possible, une liste vide, plus d'échanges avant de meilleurs souhaits, groupes, plusieurs boucles, exemplaires d'un
  jeu gardés et non gardés, identifiants faits de chiffres, germe qui départage les égalités, ordre de la liste qui ne
  change rien ;
- des propriétés sur des listes aléatoires : chaque offre change de mains une fois au plus, chaque échange est souhaité,
  personne n'échange avec soi-même ni ne reçoit un jeu deux fois, le résultat est le même pour le même germe quel que
  soit l'ordre de la liste, et, sans jeux, le solveur échange autant d'offres qu'une recherche de toutes les
  couvertures, au meilleur total de priorités ;
- l'affectation sous-jacente, comparée à une recherche de toutes les affectations sur des matrices creuses
  aléatoires ;
- le format JSON : un document écrit puis relu, et chaque refus du lecteur.

Dans le monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

## Le banc d'essai

`tests/Benchmark/TradeSolverBench.php` résout l'échange d'un congrès — 1 000 offres d'environ 330 participants de trois
offres chacun, chaque offre en souhaitant 20 ou 100 autres, une ligne sur cinq un groupe de trois, un jeu pour deux
offres de sorte que les exemplaires et leurs gardes soient courants — et lit son document JSON :

```sh
composer bench -- packages/tilttek-math-trade/tests/Benchmark
```

| Sujet | 20 souhaits par offre | 100 souhaits par offre |
|---|---|---|
| Résolution | environ 0,12 s, 16 Mo | environ 0,6 s, 84 Mo |
| Lecture du JSON | environ 14 ms, 11 Mo | environ 60 ms, 42 Mo |

Les temps sont ceux d'un portable, à titre d'ordre de grandeur ; le banc d'essai n'est pas une porte.

## Complexité

Avec V offres — plus un nœud pour chaque jeu sur lequel un participant est gardé — et E offres souhaitées, chaque offre
d'un groupe comptée :

- construire et vérifier une liste, écrire et lire son JSON, sont en O(V + E) ;
- le solveur construit le graphe en O(V + E), puis trouve une affectation de coût minimal par plus courts chemins
  d'augmentation successifs : Dijkstra sur les coûts réduits avec un tas binaire, O(E log V) pour chaque offre que le
  départ glouton laisse sans affectation, donc O(V · E log V) au pire ;
- la mémoire est en O(V + E).

En pratique, la plupart des offres prennent leur souhait le moins coûteux au départ et la plupart des chemins sont
courts : le solveur reste bien en deçà du pire cas, comme le montre le banc d'essai. Les limites par défaut — 5 000
offres, 250 000 offres souhaitées — gardent une liste résoluble en quelques secondes.

## Fuzzing

Le lecteur JSON a une cible de fuzzing, `trade-list-json`, alimentée par les germes de
`tests/Fuzz/corpus/trade-list-json` : quel que soit le document, le lecteur renvoie une liste d'échange ou la refuse par
une exception de la bibliothèque ; une liste qu'il renvoie est écrite puis relue en le même document, et se résout en
échanges où chaque offre change de mains une fois au plus.

```sh
composer fuzz -- trade-list-json
```

## Tester du code qui mène un échange

Construisez la liste dans le test, et résolvez-la avec un germe fixe : le résultat est le même à chaque exécution.

```php
use Tilttek\MathTrade\Offer;
use Tilttek\MathTrade\Participant;
use Tilttek\MathTrade\TradeList;
use Tilttek\MathTrade\TradeSolver;
use Tilttek\MathTrade\Want;

$list = new TradeList([
    new Participant('alice', [new Offer('a1', [new Want('b1')])]),
    new Participant('bob', [new Offer('b1', [new Want('a1')])]),
]);

$result = new TradeSolver()->solve($list, 'test');

self::assertSame('b1', $result->swapOf('a1')?->received);
```
