<!-- phil-dev source-sha256=bc632cccba349b64011dc87112f46148760c83e93ce1bc80b646007d4156eebe -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-mbstring` et `ext-sodium`.
- `phil/resource` 0.1, dont `ResourceReference` nomme les détenteurs et les surfaces.
- `tilttek/sets` 0.1, pour les planches et les albums d'autocollants.
- `psr/clock` et `psr/event-dispatcher` : l'horloge des placements et le répartiteur des événements, tous deux
  facultatifs.

## Installer

```sh
composer require tilttek/stickers
```

## Câbler la bibliothèque

```php
use Tilttek\Stickers\{Limits, Stickers, StickerSheets};

$stickers = new Stickers(
    $catalog,       // Contract\StickerCatalog: the stickers of the site
    $ownerships,    // Contract\OwnershipStore: the stickers each holder owns
    $placements,    // Contract\PlacementStore: where the stickers are placed
    $surfaces,      // Contract\SurfacePolicy: which surfaces each holder may use
    new Limits(perSurface: 12, perHolder: 500, minScale: 50, maxScale: 200, maxRotation: 45),
    $clock,         // a PSR-20 clock; the system's in UTC by default
    $dispatcher,    // a PSR-14 dispatcher, told of each change; null to dispatch nothing
    $moderator,     // Contract\PlacementModerator, which may hide a new placement; null for none
);

$sheets = new StickerSheets($sets, $ownerships);   // $sets: a Tilttek\Sets\Sets
```

`Stickers` et `StickerSheets` ne gardent rien entre deux appels : une seule instance sert donc toutes les requêtes d'un
*worker*. Les adaptateurs en mémoire gardent tout dans le processus, pour les tests, les scripts et un catalogue
déclaré dans la configuration.
