<!-- phil-dev source-sha256=f55d973900d862166e40bbfd73f6b6c1f143aab9251c4d1b70674d719078550d -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `mbstring`.
- `tilttek/dice` 0.3, dont un décompte lit les dés lancés, et `tilttek/dice-probability` 0.1, dont les distributions
  exactes donnent les faces d'un dé équitable.
- Une implémentation de `psr/clock`, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/dice-jail
```

## Câbler la bibliothèque

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\DiceJail\Jail\Warden;
use Tilttek\DiceJail\Statistics\DieAssessor;
use Tilttek\DiceJail\Statistics\Explanation;
use Tilttek\DiceJail\Statistics\GoodnessOfFit;

$warden = new Warden($jailStore, new NativeClock());     // Contract\JailStore: the history of each die
$assessor = new DieAssessor($rollSource);                // Contract\RollSource: the rolls of each die
$test = new GoodnessOfFit();
$explanation = new Explanation();
```

Aucun ne garde d'état entre deux appels : une seule instance sert donc toutes les requêtes d'un *worker*.
`Store\InMemoryJailStore` et `Store\InMemoryRollSource` gardent tout dans le processus, pour les tests et les scripts ;
leur `reset()` les vide.
