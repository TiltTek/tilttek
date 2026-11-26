<!-- phil-dev source-sha256=81e92cf9c3588d57de75d028cc0ca7256602cd1e38d8c095f95483f7f50daea3 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur le grand livre en
mémoire de `tilttek/points`, le magasin en mémoire et le `MockClock` de `symfony/clock` : des codes tirés, conservés et
tirés de nouveau quand ils sont pris ; des parrainages et chaque garde-fou, à la limite de chaque date ; la récompense
refusée sans première action, ou avec une action faite avant le parrainage ; les deux côtés payés, dans la devise des
conditions et depuis leur source ; la récompense redemandée, et demandée par deux processus à la fois, versée une
fois ; un paiement interrompu terminé ; le plafond, y compris deux nouveaux qui agissent à la fois ; un parrainage
d'une même personne découvert plus tard ; la lecture ; et l'effacement de l'un ou l'autre côté. Deux magasins des tests
exécutent ce qu'un autre processus fait entre une lecture et l'écriture qui la suit. Dans le monodépôt, Infection
maintient la bibliothèque à 100 % des mutants couverts.

## Tester le code qui parraine des membres

Construisez les points sur un grand livre en mémoire, le magasin en mémoire, et des premières actions que le test
règle :

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;
use Tilttek\Referrals\Referrals;
use Tilttek\Referrals\Store\InMemoryReferralStore;

$clock = new MockClock('2026-11-20 12:00:00 UTC');
$points = new Points(new InMemoryLedger(), $clock, new Currencies(new Currency('points')));
$referrals = new Referrals(new InMemoryReferralStore(), $points, $firstActions, $clock);
```

Un magasin propre à l'application se teste contre le contrat de [Points d'extension](extension-points.md) : les
contraintes d'unicité, et un règlement qui fait tenir le plafond quand deux processus règlent à la fois.
