# Installation

```sh
composer require tilttek/geek-dates
```

The package needs PHP 8.5 or later with `ext-mbstring`, `phil/almanac` 0.1, which computes the rules, and `phil/url` 0.1,
which reads the sources and needs `ext-intl` and `ext-uri`. It has no configuration and no service to wire: build a
`Catalog` where the dates are shown.

```php
use Tilttek\GeekDates\Catalog;

$catalog = Catalog::shipped(new DateTimeZone('Europe/Paris'));
```

The catalog keeps no state between two questions and never changes once built: a long-running worker may build it
once and share it between requests. `Catalog::shipped()` reads `data/dates.php`, which the opcode cache keeps.
