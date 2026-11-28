# Installation

## Requirements

- PHP 8.5 or later, with `mbstring`.
- `tilttek/dice`, which reads, checks and normalises the dice expressions.
- `symfony/yaml`, which reads and writes the YAML documents.
- `phil/url` 0.1.4 or a later 0.1, which reads the address of a source, with `ext-intl`.
- Composer 2.2 or later, whose runtime API lists the installed data packages.

## Installing

```sh
composer require tilttek/rpg-systems
```

## Wiring the library

The library has no service to configure: `SystemReader` reads documents, `SystemWriter` writes them,
`AttributionRenderer` renders notices, `Importer` imports extracts and `InstalledDataPackages` loads the data packages
Composer installed. Each is a value without state between two
calls, which one instance per application serves, in a worker as in a request.

```php
$reader = new SystemReader();
$systems = $reader->load(...glob(__DIR__.'/systems/*.yaml'));
```

Reading a large system from YAML costs more than reading it from JSON — the YAML parser dominates the time —: an
application that reads its systems at each start keeps them as JSON, which `SystemWriter::toJson()` writes, or keeps
the `Systems` it read in its cache.
