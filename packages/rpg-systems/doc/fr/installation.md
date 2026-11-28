<!-- phil-dev source-sha256=a835034d583cc3ee339abf75b71dad6969bb98f1d7b3c9bc57d4dd3685d0e94d -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `mbstring`.
- `tilttek/dice`, qui lit, vérifie et normalise les expressions de dés.
- `symfony/yaml`, qui lit et écrit les documents YAML.
- `phil/url` 0.1.4 ou une version 0.1 plus récente, qui lit l'adresse d'une source, avec `ext-intl`.
- Composer 2.2 ou une version plus récente, dont l'API d'exécution liste les paquets de données installés.

## Installer le paquet

```sh
composer require tilttek/rpg-systems
```

## Câbler la bibliothèque

La bibliothèque n'a aucun service à configurer : `SystemReader` lit des documents, `SystemWriter` en écrit,
`AttributionRenderer` produit des mentions, `Importer` importe des extraits et `InstalledDataPackages` charge les
paquets de données que Composer a installés. Chacun est une valeur sans état entre deux appels, qu'une seule instance par application sert, dans un *worker* comme dans une requête.

```php
$reader = new SystemReader();
$systems = $reader->load(...glob(__DIR__.'/systems/*.yaml'));
```

Lire un grand système depuis YAML coûte plus cher que le lire depuis JSON — l'analyseur YAML domine le temps — : une
application qui lit ses systèmes à chaque démarrage les garde en JSON, que `SystemWriter::toJson()` écrit, ou garde
dans son cache les `Systems` qu'elle a lus.
