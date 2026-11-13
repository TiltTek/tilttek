<!-- phil-dev source-sha256=c9b479ecdd4e885650db2b96dd5b189ef02961fa3c8b584a751b83468922780d -->
# Placer des autocollants

## Surfaces

Une surface est toute ressource que l'application nomme par un `ResourceReference` : `profile:1042`,
`message:0192f3c4`. Le `Contract\SurfacePolicy` que donne l'application dit si un détenteur peut y placer des
autocollants — son propre profil, ses propres messages ; il n'y a aucune valeur par défaut permissive.
`Policy\InMemorySurfacePolicy` assigne chaque surface à son détenteur.

## La transformation

Un `Transform` dit où un autocollant se trouve dans la couche d'autocollants de sa surface, et comment :

| Valeur | Unité | Bornes absolues |
|---|---|---|
| `x`, `y` | Millièmes de la largeur et de la hauteur de la couche, depuis le coin supérieur gauche, jusqu'au centre de l'autocollant | 0 à 1 000 |
| `scale` | Pourcentage de la taille naturelle de l'autocollant | 10 à 400 |
| `rotation` | Degrés entiers, dans le sens horaire | -180 à 180 |

Des positions relatives à la couche gardent un placement là où il a été mis, quelle que soit la largeur de l'écran.

## Les limites d'un site

`Limits` resserre la transformation et compte les placements :

| Limite | Valeur par défaut | Bornes absolues |
|---|---|---|
| `perSurface` | 12 | 1 à 100 |
| `perHolder` | 500 | 1 à 10 000 |
| `minScale`, `maxScale` | 50, 200 | 10 à 400, le plus petit d'abord |
| `maxRotation` | 45, dans un sens comme dans l'autre | 0 à 180 |

Les placements masqués comptent dans les limites jusqu'à ce qu'ils soient supprimés.

## Placer

`Stickers::place($holder, $request)` place un autocollant et répartit `Event\StickerPlaced` quand :

1. l'autocollant est dans le catalogue et n'est pas retiré ;
2. le détenteur le possède ;
3. la politique laisse le détenteur utiliser la surface ;
4. la transformation respecte les limites du site ;
5. la surface et le détenteur sont sous leurs limites.

Sinon elle lance `StickerRefused`, et rien n'est stocké. Le placement reçoit un identifiant de 32 caractères
hexadécimaux issu d'une source sûre, et son moment en UTC. Un modérateur, quand il y en a un, le révise avant qu'il soit
stocké — voir [Montrer et modérer](showing-and-moderating.md#modération).

## Déplacer et supprimer

`Stickers::move($holder, $placement, $transform)` déplace l'un des placements du détenteur dans les limites du site ;
`Stickers::remove($holder, $placement)` le supprime et répartit `Event\PlacementRemoved`. Le placement d'un autre
détenteur lance `StickerRefused` ; un placement inconnu lance `UnknownPlacement`.

## Requêtes d'un éditeur

`PlacementRequest::fromJson()` lit ce qu'envoie un éditeur d'autocollants, d'au plus 4 Kio et d'un seul niveau
d'imbrication, et `fromArray()` ce que donne un formulaire :

```json
{"sticker": "red-dragon", "surface": "profile:1042", "x": 800, "y": 120, "scale": 120, "rotation": -10}
```

`scale` vaut 100 par défaut et `rotation` 0. Une clé inconnue, une clé manquante, une valeur du mauvais type, une
surface qui n'est pas une référence de ressource ou une valeur hors des bornes absolues lance `InvalidSticker`, jamais
une autre exception ; les limites du site sont vérifiées quand la requête est placée.

## Concurrence

Un stockage partagé par plusieurs processus compte et ajoute dans une seule transaction — un verrou sur la surface et
sur le détenteur —, de sorte que deux placements au même moment ne dépassent jamais ensemble une limite.
