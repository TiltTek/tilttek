<!-- phil-dev source-sha256=cd7658ac4705f19aaa7e79889dacd414cbbc72940ea53a7dcc99dc3f5b9d0a6e -->
# tilttek/stickers

**Des autocollants virtuels qu'un membre place sur son profil ou ses messages : un catalogue avec alternatives
textuelles et crédits, des autocollants possédés, des placements bornés, des limites de site, la modération, et des
planches complétées comme des ensembles.**

Un site tient un catalogue d'autocollants, chacun étant une référence à une image que sert l'application, avec
l'alternative textuelle que chaque autocollant possède, sa licence et son attribution. Les membres reçoivent des
autocollants — en récompense, par achat, en cadeau — et placent ceux qu'ils possèdent sur les surfaces que
l'application leur laisse utiliser : leur profil, leurs messages, toute ressource qu'elle nomme. Un placement se trouve
dans la couche d'autocollants de sa surface, à une position, une échelle et une rotation que le site borne ; le site
limite le nombre d'autocollants qu'une surface contient et le nombre qu'un membre place. La modération masque un
placement, tout de suite par un modérateur que l'application branche ou plus tard à la main, et le rétablit.

Les autocollants ne gênent jamais le contenu : un spectateur peut les désactiver, et la bibliothèque fournit à un
gabarit chaque placement avec l'alternative textuelle et le crédit de l'autocollant. Les planches et les albums
d'autocollants sont des ensembles de `tilttek/sets`, complétés à partir des autocollants qu'un membre possède, avec les
récompenses qu'ils déclarent.

La bibliothèque ne stocke aucune image et ne garde aucun état entre deux appels : le catalogue, les autocollants
possédés et les placements vivent derrière des ports, avec des adaptateurs en mémoire. C'est une bibliothèque PHP
simple.

## État

| Partie | État |
|---|---|
| `Sticker`, `Contract\StickerCatalog` : références d'images, alternatives textuelles, licences, attributions, autocollants retirés | Implémenté et testé |
| `Stickers` : autocollants accordés et retirés, placés, déplacés, supprimés, masqués et rétablis, montrés à un spectateur | Implémenté et testé |
| `Transform`, `Limits`, `PlacementRequest` : positions, échelles et rotations bornées, limites d'un site, requêtes lues depuis des formulaires et du JSON | Implémenté et testé |
| `Contract\SurfacePolicy`, `Contract\PlacementModerator`, `Verdict` : à qui appartiennent les surfaces, et modération des nouveaux placements | Implémenté et testé |
| `ViewerPreferences`, `PlacementView` : autocollants désactivés par un spectateur, alternatives textuelles et crédits | Implémenté et testé |
| `StickerSheets` : planches et albums comme ensembles de `tilttek/sets` | Implémenté et testé |
| Événements de chaque changement, identifiants seulement | Implémenté et testé |
| Adaptateurs en mémoire de chaque port | Implémenté et testé |
| Un adaptateur de base de données, un gabarit ou un contrôleur Stimulus | Non fourni : l'application dessine la couche et implémente les stockages |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Le catalogue](doc/fr/catalogue.md) | Autocollants, leurs images, alternatives textuelles et crédits, retrait, octroi et reprise. |
| [Placer des autocollants](doc/fr/placing-stickers.md) | Surfaces, transformation, limites d'un site, déplacement et suppression, requêtes d'un éditeur. |
| [Montrer et modérer](doc/fr/showing-and-moderating.md) | Accessibilité, spectateurs qui désactivent les autocollants, modération, événements et effacement. |
| [Planches et albums](doc/fr/sheets.md) | Ensembles d'autocollants, leurs paliers et leurs récompenses. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `StickersException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Phil\Resource\ResourceReference;
use Tilttek\Stickers\{PlacementRequest, Sticker, Stickers, ViewerPreferences};
use Tilttek\Stickers\Policy\InMemorySurfacePolicy;
use Tilttek\Stickers\Store\{InMemoryOwnershipStore, InMemoryPlacementStore, InMemoryStickerCatalog};

$catalog = new InMemoryStickerCatalog([
    new Sticker('red-dragon', 'stickers/red-dragon.webp', 'A red dragon curled up asleep', 'CC-BY-4.0', 'Art by A. Painter'),
]);
$member = ResourceReference::of('member', '1042');
$profile = ResourceReference::of('profile', '1042');
$surfaces = new InMemorySurfacePolicy();
$surfaces->assign($profile, $member);

$stickers = new Stickers($catalog, new InMemoryOwnershipStore(), new InMemoryPlacementStore(), $surfaces);
$stickers->grant($member, 'red-dragon');
$stickers->place($member, PlacementRequest::fromJson('{"sticker":"red-dragon","surface":"profile:1042","x":800,"y":120,"rotation":-10}'));

foreach ($stickers->onSurface($profile, new ViewerPreferences(showStickers: true)) as $view) {
    $view->alt();        // 'A red dragon curled up asleep'
    $view->credit();     // 'Art by A. Painter — CC-BY-4.0'
}
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-mbstring` et `ext-sodium` ; `phil/resource` 0.1, `psr/clock`,
`psr/event-dispatcher` et `tilttek/sets` 0.1.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
