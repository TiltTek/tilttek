<!-- phil-dev source-sha256=027fda7f788dfd1398d09e717a7b9fe1d3ce13922cb9ae9031bb019544821278 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/stickers` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.4-alpha - 2026-11-24

### Modifié

- `tilttek/sets` 0.3 requis : 0.2 n'est plus accepté.

## 0.1.3-alpha - 2026-11-20

### Modifié

- `tilttek/sets` 0.2 ou 0.3 requis : 0.1 n'est plus accepté.

## 0.1.2-alpha - 2026-11-17

### Modifié

- `tilttek/sets` 0.3 accepté, tout comme 0.1 et 0.2.

## 0.1.1-alpha - 2026-11-14

### Modifié

- `tilttek/sets` 0.2 accepté, tout comme 0.1.

### Corrigé

- `Stickers::revoke()` retire les placements avant de reprendre l'autocollant, pour qu'une révocation qui a échoué à
  mi-chemin soit reprise en l'appelant de nouveau, et qu'un appel pour un autocollant qui n'est plus possédé retire
  quand même les placements qui en restent ; elle renvoie alors false et n'annonce rien, comme avant.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `Sticker` : un autocollant du catalogue avec une référence à son image — jamais une référence `javascript:`,
  `vbscript:`, `data:` ou `file:` —, une alternative textuelle requise de 1 à 250 caractères, une licence et une
  attribution facultatives, et son retrait.
- `Stickers` : des autocollants accordés une seule fois et révoqués avec leurs placements ; placés sur les surfaces que
  la politique laisse chaque détenteur utiliser, quand ils sont possédés, non retirés et dans les limites du site ;
  déplacés et retirés par leur détenteur seulement ; cachés pour une raison et rétablis par la modération ; montrés à
  une personne, dans l'ordre de placement, à moins qu'elle les ait désactivés ; l'effacement d'un détenteur et d'une
  surface.
- `Transform` et `Limits` : des positions en millièmes de la couche des autocollants, des échelles de 10 à 400 pour cent
  et des rotations de -180 à 180 degrés, resserrées par le site, qui limite aussi les autocollants d'une surface (12 par
  défaut, 100 au plus) et d'un détenteur (500 par défaut, 10 000 au plus).
- `PlacementRequest` : une demande lue depuis un formulaire ou depuis du JSON d'au plus 4 Kio, chaque défaut une
  `InvalidSticker`.
- `Placement` : un placement avec un identifiant tiré d'une source sûre et son moment en UTC.
- `Contract\SurfacePolicy`, `Contract\PlacementModerator` et `Verdict` : à qui sont les surfaces, sans valeur par défaut
  permissive, et un nouveau placement caché tout de suite pour une raison.
- `ViewerPreferences` et `PlacementView` : une personne qui désactive les autocollants, et chaque placement montré avec
  l'alternative textuelle et le crédit de l'autocollant.
- `StickerSheets` : des feuilles d'autocollants et des albums sous forme d'ensembles de `tilttek/sets`, avec leurs
  paliers et leurs récompenses, complétés une seule fois à partir des autocollants que possède un détenteur.
- `Event\StickerGranted`, `Event\StickerRevoked`, `Event\StickerPlaced`, `Event\PlacementHidden`,
  `Event\PlacementRestored` et `Event\PlacementRemoved`, avec des identifiants seulement.
- Les détenteurs et les surfaces désignés par un identifiant stable, jamais par une adresse courriel.
- `Contract\StickerCatalog`, `Contract\OwnershipStore`, `Contract\PlacementStore` et leurs adaptateurs en mémoire, avec
  `Policy\InMemorySurfacePolicy`.
- La cible de fuzzing d'une demande de placement et les bancs d'essai d'une surface pleine.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
