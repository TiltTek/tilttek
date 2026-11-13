<!-- phil-dev source-sha256=1b7f9b6fc1a03484137136c2f6d4438f0e485cfc2e4145b043e18b567e4ef02f -->
# Installation

## Prérequis

PHP 8.5 ou une version plus récente. La bibliothèque n'a besoin d'aucun autre paquet.

## Installer

```sh
composer require tilttek/wargame-army
```

## Câbler la bibliothèque

```php
use Tilttek\WargameArmy\ArmyValidator;
use Tilttek\WargameArmy\Export\{HtmlExport, TextExport};

$validator = new ArmyValidator(
    $catalogues,       // Contract\CatalogueProvider: the application's catalogues, by code
    ...$constraints,   // Contract\Constraint: the application's own rules, checked on every list
);

$text = new TextExport();
$html = new HtmlExport();
```

`ArmyValidator` et les exportations ne gardent rien entre deux appels : une seule instance sert toutes les requêtes
d'un *worker*. Lire un catalogue de 2 000 unités avec dix options chacune prend une fraction notable de seconde : une
application lit ses catalogues une seule fois — au démarrage, ou depuis son cache — et les donne à
`Catalog\InMemoryCatalogueProvider` ou à un fournisseur à elle.
