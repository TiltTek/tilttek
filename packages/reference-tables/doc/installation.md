# Installation

```sh
composer require tilttek/reference-tables
```

The library needs PHP 8.5 or later with the `mbstring` extension. It brings `tilttek/rpg-systems`, `tilttek/dice` and
`symfony/yaml`.

A first table, read from a document of your own:

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

Read the documents once, when the application warms up or its cache is rebuilt, and keep the `TableCatalogue`: it is
plain data that every request shares.
