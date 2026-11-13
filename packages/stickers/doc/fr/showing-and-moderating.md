<!-- phil-dev source-sha256=b541b11a41b8ebd91f4489d9ff11c6fd984738c0b6d67a4bfc7c1eb5024076d2 -->
# Montrer et modérer

## Montrer une surface

`Stickers::onSurface($surface, $viewer)` donne les placements qu'un spectateur voit, dans l'ordre où ils ont été
placés — le dernier dessiné par-dessus —, chacun sous la forme d'un `PlacementView` avec son placement et son
autocollant. Elle laisse de côté :

- tous les placements, quand le spectateur a désactivé les autocollants avec `ViewerPreferences::stickersOff()` ;
- un placement masqué par la modération ;
- un placement dont l'autocollant est sorti du catalogue.

## Accessibilité

Les autocollants décorent une surface ; ils ne gênent jamais ce qu'elle dit. La bibliothèque fournit ce dont un gabarit
a besoin pour les dessiner ainsi :

- chaque autocollant a une alternative textuelle, `PlacementView::alt()`, pour le `alt` de l'image, et un crédit,
  `PlacementView::credit()` — l'attribution, puis la licence —, pour une légende ou un titre ;
- un spectateur peut désactiver les autocollants : gardez ce choix dans les préférences du spectateur et passez-le à
  `onSurface()`, qui ne donne alors rien ;
- les positions sont relatives à une couche d'autocollants, que le gabarit dessine à côté d'une marge de la surface ou
  par-dessus, jamais par-dessus son texte, et que les clics traversent ;
- les rotations et les échelles sont bornées par le site, et la bibliothèque n'anime rien.

## Modération

Un `Contract\PlacementModerator` révise chaque nouveau placement avant qu'il soit stocké et retourne un `Verdict` :
`Verdict::show()`, ou `Verdict::hide('review')` pour un motif que nomme l'application. Un placement masqué est stocké,
annoncé par `Event\StickerPlaced` avec `hidden: true`, et montré à personne.

`Stickers::hide($placement, $reason)` masque un placement plus tard — un signalement traité, une révision — et répartit
`Event\PlacementHidden` ; `Stickers::restore($placement)` le montre de nouveau et répartit `Event\PlacementRestored`.
L'application vérifie qui peut modérer avant de les appeler.

## Événements

Chaque événement ne porte que des identifiants et de petites valeurs, jamais les textes d'un autocollant :

| Événement | Annoncé quand |
|---|---|
| `Event\StickerGranted` | Un détenteur reçoit un autocollant qu'il ne possédait pas |
| `Event\StickerRevoked` | Un autocollant est repris, avec le nombre de placements supprimés |
| `Event\StickerPlaced` | Un autocollant est placé, montré ou masqué |
| `Event\PlacementHidden`, `Event\PlacementRestored` | La modération masque ou rétablit un placement |
| `Event\PlacementRemoved` | Un détenteur supprime l'un de ses placements |

## Effacement

`Stickers::eraseHolder($holder)` supprime les placements d'un détenteur et les autocollants qu'il possède, pour
l'effacement d'un membre, et retourne combien de chaque. `Stickers::eraseSurface($surface)` supprime les placements
d'un profil ou d'un message supprimé. Les complétions des planches d'autocollants vivent dans `tilttek/sets` :
`Sets::eraseHolder()` les supprime.
