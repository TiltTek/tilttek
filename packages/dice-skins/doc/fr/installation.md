<!-- phil-dev source-sha256=74823db5102f4c1eebe37b7d3cc6b2956ff3c7ea7d830c93a1cebd776d3f5b1d -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `mbstring`.
- `phil/resource` 0.1, dont `ResourceReference` nomme un membre.
- `tilttek/collections` 0.1, dont l'étagère de dés donne les dés qu'un membre possède.
- Facultatif : `tilttek/dice-bundle` 0.2.0-alpha ou une version plus récente, dont le lanceur porte l'apparence par
  `DiceBundle\DiceSkinsAppearance`, comme l'explique [Lancer avec tilttek/dice-bundle](dice-bundle.md).

## Installer

```sh
composer require tilttek/dice-skins
```

## Câbler la bibliothèque

`DiceSkins` reçoit le catalogue des skins, le stockage des choix des membres, et facultativement les dés possédés et la
clé du skin par défaut :

```php
use Tilttek\DiceSkins\BaseSkins;
use Tilttek\DiceSkins\Collections\ShelfOwnedDice;
use Tilttek\DiceSkins\DiceSkins;
use Tilttek\DiceSkins\Store\FixedSkinCatalog;

$skins = new DiceSkins(
    FixedSkinCatalog::withBase($siteSkinsJson),          // Contract\SkinCatalog: the base skins and the site's own
    $choices,                                            // Contract\SkinChoiceStore: the members' choices
    new ShelfOwnedDice($collections, $shelf, 'en'),      // Contract\OwnedDice: Store\NoOwnedDice by default
    BaseSkins::DEFAULT,                                  // the key of the default skin: ivory
);
```

`DiceSkins` ne garde rien entre deux appels : une seule instance sert donc toutes les requêtes d'un *worker*.
`InMemorySkinChoiceStore` garde les choix dans le processus, pour les tests et les scripts ; une application les garde
dans son propre stockage, une ligne par membre et par nombre de faces, comme le décrit
[Points d'extension](extension-points.md).
