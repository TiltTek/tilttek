<!-- phil-dev source-sha256=1e77184bd81f20d8e36733b7d799730fe7b8dc69a6d8d9c53924d58b85a7f4df -->
# Installation

## Prérequis

PHP 8.5 ou plus récent, et `tilttek/dice`, qui lit les expressions. La bibliothèque n'a aucune autre dépendance : elle
gère elle-même ses nombres exacts, sans les extensions `bcmath` ou `gmp`.

## Installer

```sh
composer require tilttek/dice-probability
```

Une application Symfony prend `tilttek/dice-probability-bundle`. Ce bundle branche la bibliothèque sur les dés, les
notations et les limites de `tilttek/dice-bundle`, et ajoute les fonctions Twig, le tableau, le graphique et le Live
Component.

## Première utilisation

```php
use Tilttek\Dice\DiceRoller;
use Tilttek\DiceProbability\Probabilities;

$dice = new DiceRoller();
$probabilities = new Probabilities();

$distribution = $probabilities->distribution($dice->parse('4d6kh3'));
(string) $distribution->probability(18);   // "21/1296"
$distribution->mean()->toFloat();           // 12.2446…
```

Quand les expressions nomment des dés personnalisés, donnez à `Probabilities` les mêmes `CustomDice` que le
`DiceRoller` qui les analyse, et les mêmes `Limits` ; voir [Distributions](distributions.md).
