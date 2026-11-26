<!-- phil-dev source-sha256=db3906e18fbe1b7f7748d33b37d78538a12f35542a4ae021df715c603dd30b31 -->
# Installation

```sh
composer require tilttek/geek-dates
```

Le paquet demande PHP 8.5 ou une version plus récente avec `ext-mbstring`, `phil/almanac` 0.1, qui calcule les
règles, et `phil/url` 0.1, qui lit les sources et demande `ext-intl` et `ext-uri`. Il n'a aucune configuration et aucun
service à brancher : construisez un `Catalog` là où les dates sont montrées.

```php
use Tilttek\GeekDates\Catalog;

$catalog = Catalog::shipped(new DateTimeZone('Europe/Paris'));
```

Le catalogue ne garde aucun état entre deux questions et ne change jamais une fois construit : un *worker* de longue
durée peut le construire une fois et le partager entre les requêtes. `Catalog::shipped()` lit `data/dates.php`, que le
cache d'opcodes conserve.
