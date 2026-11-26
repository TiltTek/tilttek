<!-- phil-dev source-sha256=8a61277e22ad01dc78b46990f6e0f5f9ace2f74f403f38a70234d959cb7fb712 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur le journal en
mémoire de `tilttek/activity` et le `MockClock` de `symfony/clock` : l'activité qu'enregistrent les remerciements et les
événements qu'ils annoncent ; les clés, construites ou fournies, et les remerciements donnés de nouveau sous elles ; les
refus de soi-même, d'un destinataire qui refuse, du plafond quotidien — à minuit dans le fuseau horaire du membre, une
seconde avant et au moment du passage — et de la limite par destinataire, à la limite de sa période ; les remerciements
retirés comptés dans les deux ; les messages et leur liste ; les limites et leurs bornes ; le retrait et l'annulation ;
les pages de remerciements reçus et donnés ; l'absence de toute lecture de ce qu'un autre membre a reçu ; le décompte
des remerciements donnés, par période ; et l'effacement d'un membre par le journal. Dans le monodépôt, Infection
maintient la bibliothèque à 100 % des mutants couverts.

## Tester le code qui donne des remerciements

Construisez un journal en mémoire et une horloge que le test déplace :

```php
use Symfony\Component\Clock\MockClock;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Kudos\Kudos;
use Tilttek\Kudos\Limits;

$clock = new MockClock('2026-11-20 12:00:00', 'UTC');
$kudos = new Kudos(new Journal(new InMemoryActivityLog(), $clock), $clock, limits: new Limits(daily: 1));

$kudos->give($giver, $receiver, 'help');
$clock->modify('+1 day'); // the cap is counted again
```
