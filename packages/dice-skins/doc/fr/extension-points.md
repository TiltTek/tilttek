<!-- phil-dev source-sha256=039567753ebaf6783eb6dd967a456fe0af66c294482c23ccf86885bf4ab648c0 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Fourni |
|---|---|---|
| `Contract\SkinCatalog` | Les skins que le site offre : depuis un fichier, une administration, une base de données | `Store\FixedSkinCatalog` |
| `Contract\SkinChoiceStore` | Le stockage des choix des membres | `Store\InMemorySkinChoiceStore` |
| `Contract\OwnedDice` | Les dés qu'un membre possède, et celui qu'il a choisi pour un nombre de faces | `Collections\ShelfOwnedDice`, `Store\NoOwnedDice` |
| Le skin par défaut | La clé du skin que reçoit un membre qui n'a rien choisi | `BaseSkins::DEFAULT`, `ivory` |

Chaque point est passé au constructeur de `DiceSkins`, qui ne garde rien entre deux appels.

La bibliothèque remplit aussi un point d'extension d'un autre paquet : `DiceBundle\DiceSkinsAppearance` implémente le
port `DiceAppearance` de `tilttek/dice-bundle`, comme l'explique [Lancer avec tilttek/dice-bundle](dice-bundle.md).

## Stocker les choix

Un `SkinChoiceStore` garde un choix par membre pour tous ses dés — `$sides` nul — et un par membre et nombre de
faces :

- `save()` remplace le choix du membre pour le même nombre de faces, ou pour tous ses dés ;
- `find()` le donne, ou null ;
- `remove()` indique s'il y en avait un ;
- `ofMember()` liste d'abord le choix pour tous les dés, puis les autres par nombre de faces ;
- `eraseMember()` supprime tous les choix du membre et en retourne le nombre, pour l'effacement d'un membre.

`SkinChoice::toArray()` donne la ligne à conserver : le membre sous la forme `kind:identifier`, la clé du skin et le
nombre de faces.

## Des dés possédés venus d'ailleurs

Une application dont les membres possèdent des dés ailleurs implémente `Contract\OwnedDice` : `ownedBy()` liste des
`OwnedDie` — l'article, son nom, son nombre de faces, sa couleur, sa matière en texte libre et l'exemplaire du membre —,
et `chosen()` donne le dé que le membre lance pour un nombre de faces, ou null.
