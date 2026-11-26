<!-- phil-dev source-sha256=6ca9d514bc764cd386b9281ae6b97e01e1f008105b5298ed386ec5c618af5915 -->
# Installation

## Prérequis

PHP 8.5 ou une version plus récente. La bibliothèque n'a aucune autre dépendance : le germe est tiré avec l'extension
`Random` de PHP, et les listes sont lues avec ses fonctions JSON.

## Installer

```sh
composer require tilttek/math-trade
```

## Utiliser la bibliothèque

Rien n'est à câbler. `TradeSolver` et `TradeListJson` ne prennent aucun argument et ne gardent aucun état entre deux
appels : une seule instance sert toutes les requêtes, en mode *worker* comme en mode requête.

```php
use Tilttek\MathTrade\TradeListJson;
use Tilttek\MathTrade\TradeSolver;

$list = new TradeListJson()->decode($json);
$result = new TradeSolver()->solve($list, $seed);
```

Le temps du solveur croît avec la taille de la liste — voir [Tests](testing.md#complexité) — : une application qui
laisse des membres téléverser des listes les résout hors de la requête, dans un *handler* de Messenger par exemple,
avec un message qui porte l'identifiant de l'échange et rien d'autre.
