# Installation

```sh
composer require tilttek/seasonal-events
```

The library needs PHP 8.5 or later, `phil/recurrence`, `phil/resource` and a PSR-20 clock, such as `symfony/clock`.

## Wiring

`SeasonalEvents` takes a `SeasonCatalogue` and a clock:

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

In a Symfony application, register it as a service and alias `ActiveContent` to it, so that the other packages read
what is on through the port:

```yaml
services:
    Tilttek\SeasonalEvents\Contract\SeasonCatalogue:
        class: Tilttek\SeasonalEvents\Store\InMemorySeasonCatalogue
        factory: [App\Seasons\Seasons, catalogue]
    Tilttek\SeasonalEvents\SeasonalEvents:
        arguments: ['@Tilttek\SeasonalEvents\Contract\SeasonCatalogue', '@clock']
    Tilttek\SeasonalEvents\Contract\ActiveContent: '@Tilttek\SeasonalEvents\SeasonalEvents'
```

`SeasonalEvents` keeps no state between two questions: it is safe in a worker that serves many requests.
