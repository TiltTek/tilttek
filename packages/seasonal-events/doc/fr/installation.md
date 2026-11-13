<!-- phil-dev source-sha256=ad3db47e24cce74c63a62acbfc67ea8379f10bc9e915cd887821958588c17af3 -->
# Installation

```sh
composer require tilttek/seasonal-events
```

La bibliothèque exige PHP 8.5 ou une version plus récente, `phil/recurrence`, `phil/resource` et une horloge PSR-20,
comme `symfony/clock`.

## Raccordement

`SeasonalEvents` reçoit un `SeasonCatalogue` et une horloge :

```php
use Symfony\Component\Clock\Clock;
use Tilttek\SeasonalEvents\SeasonalEvents;
use Tilttek\SeasonalEvents\SeasonReader;
use Tilttek\SeasonalEvents\Store\InMemorySeasonCatalogue;

$seasons = new SeasonalEvents(
    new InMemorySeasonCatalogue(SeasonReader::all(require 'config/seasons.php')),
    new Clock(),
);
```

Dans une application Symfony, enregistrez-le comme service et faites de `ActiveContent` un alias vers lui, afin que
les autres paquets lisent ce qui est en cours par le port :

```yaml
services:
    Tilttek\SeasonalEvents\Contract\SeasonCatalogue:
        class: Tilttek\SeasonalEvents\Store\InMemorySeasonCatalogue
        factory: [App\Seasons\Seasons, catalogue]
    Tilttek\SeasonalEvents\SeasonalEvents:
        arguments: ['@Tilttek\SeasonalEvents\Contract\SeasonCatalogue', '@clock']
    Tilttek\SeasonalEvents\Contract\ActiveContent: '@Tilttek\SeasonalEvents\SeasonalEvents'
```

`SeasonalEvents` ne garde aucun état entre deux questions : il est sûr dans un *worker* qui sert plusieurs requêtes.
