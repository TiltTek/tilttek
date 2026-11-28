<!-- phil-dev source-sha256=9f024fa108f66f4df9fd826b7e9e6549a1c3d6d98608714b5f484700cbc21623 -->
# Installation

```sh
composer require tilttek/reference-tables
```

La bibliothèque exige PHP 8.5 ou une version plus récente avec l'extension `mbstring`. Elle amène `tilttek/rpg-systems`,
`tilttek/dice` et `symfony/yaml`.

Une première table, lue dans un document de votre cru :

```php
use Tilttek\ReferenceTables\Format\Syntax;
use Tilttek\ReferenceTables\Format\TableReader;

$tables = new TableReader()->parse(<<<'YAML'
    format: tilttek-reference-tables/1
    source: {title: My notes, licence: owned}
    tables:
      - key: weather
        names: Weather
        die: 1d6
        columns: [{key: sky, names: Sky}]
        rows:
          - {range: 1-3, sky: Clear}
          - {range: 4-5, sky: Clouds}
          - {range: 6, sky: Storm}
    YAML, Syntax::Yaml);

$tables->get('weather')->lookup(4)->display('sky'); // "Clouds"
```

Lisez les documents une seule fois, quand l'application se réchauffe ou que son cache est reconstruit, et gardez le
`TableCatalogue` : ce sont des données simples que toutes les requêtes partagent.
