<!-- phil-dev source-sha256=5b064394d39aa51509ff84c41a9d382243727ad85cb43e77d47ca8b747ec95df -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `tilttek/dice` 0.1, qui lit la notation des dés et lance une expression à partir d'un germe.

## Installer

```sh
composer require tilttek/secret-rolls
```

## Brancher la bibliothèque

`SecretRolls` reçoit un dépôt de stockage et, facultativement, un publicateur, un `DiceRoller` et le générateur
aléatoire dont les germes sont tirés :

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\SecretRolls\SecretRolls;

$rolls = new SecretRolls(
    $store,                              // Contract\SecretRollStore: the journals and the sealed rolls
    $publisher,                          // Contract\JournalPublisher: Store\NoPublisher by default
    new DiceRoller(),                    // the notation, the dialects and the limits of the dice
    new Randomizer(new Secure()),        // the source of the seeds: the system's secure source by default
);
```

Passez le même `DiceRoller` — les mêmes limites — à `RollVerifier`, afin que ce que le meneur de jeu lance, le
vérificateur le lise. `SecretRolls` et `RollVerifier` ne gardent rien entre deux appels : une seule instance sert donc
toutes les requêtes d'un *worker* ; `InMemorySecretRollStore` garde tout dans le processus, pour les tests et les
scripts.
