<!-- phil-dev source-sha256=94c55282e42e13a32d9927f99a90915dd4f138fde7b27a222aab4d79ca1cc25f -->
# Le blason

`Blazon\Blazonry::write()` écrit le blason d'un écu dans une `Language`, l'anglais par défaut ; `Language::ofLocale()`
lit `fr`, `fr_CA` ou `fr-CA`. Le blason commence par une majuscule et n'a pas de point final.

## En anglais

Le champ d'abord, puis une virgule et ce qui s'y trouve. Le nombre, le nom et la disposition d'un meuble viennent avant
son émail :

| Écu | Blason |
|---|---|
| Un champ uni | `Azure` |
| Trois meubles | `Azure, three mullets in fess Or` |
| Des meubles sur la pièce | `Azure, on a fess Or three mullets gules` |
| … dans l'émail du champ | `Azure, on a chief Or three fleurs-de-lis of the field` |
| Des meubles autour de la pièce | `Azure, a fess Or between three mullets argent` |
| … dans son émail, écrit une seule fois | `Azure, a fess between two mullets Or` |
| Un chef | `Azure, three mullets argent, a chief Or` / `Azure, three mullets and a chief Or` |
| Une bordure | `Azure, a mullet Or within a bordure gules` |
| Une brisure | `…, a label argent`, `…, overall a bendlet sable` |

Les émaux s'écrivent en minuscules, sauf Or. Un tourteau (*roundel*) est nommé par son émail et écrit sans lui : bezant
(Or), plate (argent), torteau (gules), hurt (azure), pomme (vert), pellet (sable), golpe (purpure) ; un tourteau de
fourrure reste un *roundel*. Un seul meuble prend « a », ou « an » devant une voyelle.

## En français

Le champ d'abord, puis chaque élément introduit par « à » et son article, séparés par des virgules ; un champ divisé
prend une virgule avant le premier. L'émail vient après le nom et avant la disposition :

| Écu | Blason |
|---|---|
| Un champ uni | `D’azur plein` |
| Un meuble | `D’azur à l’étoile d’or`, `D’azur au croissant d’argent`, `D’azur à la billette d’or` |
| Trois meubles | `D’azur à trois étoiles d’or rangées en fasce`, `… trois losanges d’argent posés en pal` |
| Des meubles sur la pièce | `D’azur à la fasce d’or chargée de trois étoiles de gueules`, `… au chevron d’or chargé d’un croissant` |
| … dans l'émail du champ | `D’azur au chef d’or chargé de trois fleurs de lis du champ` |
| Des meubles autour de la pièce | `D’azur à la fasce d’or accompagnée de trois étoiles d’argent` |
| … de part et d'autre d'un pal, dans son émail | `D’azur au pal d’or accosté de deux annelets du même` |
| Un chef ou une bordure | `D’azur à trois étoiles d’argent, au chef d’or` |
| Une brisure | `…, au lambel d’argent`, `…, au bâton de sable brochant sur le tout` |

La grammaire que suit le rédacteur :

- **Les articles** se contractent : « au chef », « à la fasce », « à l’étoile » ; un seul meuble sur le champ prend
  l'article défini, un seul meuble sur une pièce ou autour d'elle « d’un » ou « d’une ».
- **L'élision** : « d’or », « d’argent », « d’azur », « d’hermine » ; « de gueules », « de sinople », « de sable »,
  « de pourpre », « de vair ».
- **L'accord** : « chargé », « accompagné » et « accosté » s'accordent avec la pièce — la fasce, la bande, la barre, la
  croix et la bordure sont féminines — ; « rangés » et « posés » s'accordent avec les meubles — l'étoile, la macle, la
  billette et la fleur de lis sont féminines.
- **Les pluriels** : tourteau, tourteaux ; fleur de lis, fleurs de lis.
- Un tourteau est un **besant** sur un métal et un **tourteau** autrement, et garde son émail.
- Un champ uni seul est **plein** ; le bâton est **brochant sur le tout** quand il recouvre quelque chose.

## Lire un blason

La lecture d'un blason écrit pour le remettre dans un `Coat` n'est pas commencée.
