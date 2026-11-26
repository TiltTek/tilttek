<!-- phil-dev source-sha256=d09c4eb3d1e5c9649a54ed4a4f85affdaff3605110094d1aca58cd0e1148a1f1 -->
# Installation

```sh
composer require tilttek/rpg-calendar-events
```

Le paquet demande PHP 8.5 ou une version plus récente avec `ext-mbstring`, `phil/almanac` 0.1, qui calcule les
règles, et `phil/url` 0.1, qui lit les sources et demande `ext-intl` et `ext-uri`. Il n'a aucune configuration et aucun
service à brancher : construisez un `Catalog` là où les événements sont montrés.

```php
use Tilttek\RpgCalendarEvents\Catalog;

$catalog = Catalog::shipped(new DateTimeZone('Europe/Paris'));
```

Le catalogue ne garde aucun état entre deux questions et ne change jamais une fois construit : un *worker* de longue
durée peut le construire une fois et le partager entre les requêtes. `Catalog::shipped()` lit `data/events.php`, que le
cache d'opcodes conserve.
