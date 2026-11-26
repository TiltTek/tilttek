<!-- phil-dev source-sha256=f74162886de9d93ed88dcccb3e174cf27d0e80cfd088941eeb363cf850bfb1c4 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur le grand livre en
mémoire de `tilttek/points`, le magasin en mémoire et le `MockClock` de `symfony/clock` : des questions posées, reposées
et posées différemment ; des choix faits et changés ; l'échéance, une seconde avant et à l'instant même ; une
résolution qui se glisse avant l'écriture d'une prédiction ; le score de Brier sur des valeurs connues et son arrondi ;
le règlement versé une fois quand il est relancé ou lancé par deux processus à la fois, lu par pages, et non décalé par
un membre effacé entre-temps ; l'annulation ; la lecture ; et l'effacement. Dans le monodépôt, Infection maintient la
bibliothèque à 100 % des mutants couverts.

## Tester le code qui pose des questions

Construisez les points sur un grand livre en mémoire, le magasin en mémoire, et une horloge que le test fait passer
au-delà de l'échéance :

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;
use Tilttek\Predictions\Predictions;
use Tilttek\Predictions\Store\InMemoryPredictionStore;

$clock = new MockClock('2026-11-20 12:00:00 UTC');
$points = new Points(new InMemoryLedger(), $clock, new Currencies(new Currency('points')));
$predictions = new Predictions(new InMemoryPredictionStore(), $points, $clock);
```
