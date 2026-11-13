<!-- phil-dev source-sha256=8539782c9a3e5de2c1337528869ce359920961c8e20871e3a82ac484b4777387 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `mbstring` et `sodium`.

## Installer

```sh
composer require tilttek/heraldry
```

## Câbler la bibliothèque

Chaque service est un objet simple sans état entre deux appels : une seule instance sert donc toutes les requêtes d'un
*worker* :

```php
use Tilttek\Heraldry\Blazon\Blazonry;
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Svg\Palette;
use Tilttek\Heraldry\Svg\SvgRenderer;
use Tilttek\Heraldry\TinctureRule;

$generator = new CoatGenerator(furs: true);        // whether a field may be of ermine or vair
$blazonry = new Blazonry();                        // the English and French writers, replaceable
$renderer = new SvgRenderer(new Palette(), $blazonry);
$rule = new TinctureRule();
```

Dans une application Symfony, enregistrez-les comme services ; aucun n'a besoin de configuration.
