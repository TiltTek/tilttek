<!-- phil-dev source-sha256=f12b9463dd3f3419f315f9744006f7071d1e47aa0174a914647bfed86c0ca2a7 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `tilttek/activity`, le journal où les remerciements sont enregistrés.
- `phil/resource`, dont les références désignent les membres et les objets.
- Une horloge `psr/clock`, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/kudos
```

## Câbler la bibliothèque

`Kudos` reçoit le journal et l'horloge de l'application ; tout le reste a une valeur par défaut :

```php
use Tilttek\Kudos\Kudos;
use Tilttek\Kudos\Limits;
use Tilttek\Kudos\Messages;
use Tilttek\Kudos\TimeZone\SiteTimeZone;

$kudos = new Kudos(
    $journal,                              // Tilttek\Activity\Journal
    $clock,                                // Psr\Clock\ClockInterface
    Messages::defaults(),                  // the closed list of messages
    new Limits(daily: 5, perReceiver: 3),  // five a day, three to one member in seven days
    $preferences,                          // Contract\KudosPreferences; Preferences\AcceptAll by default
    new SiteTimeZone(new DateTimeZone('America/Toronto')), // Contract\MemberTimeZones; UTC by default
    [$observer],                           // Contract\KudosObserver, told of thanks given and cancelled
);
```

Quand le journal déclare ses verbes, la déclaration inclut `kudos.given` (`Kudos::VERB`), ou `kudos.*`.

`Kudos` ne garde aucun état entre deux appels : une seule instance sert toutes les requêtes, en mode *worker* comme en
mode requête. Le journal et les ports qu'il reçoit tiennent aussi cette promesse.
