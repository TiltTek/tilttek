# Blazon

`Blazon\Blazonry::write()` writes the blazon of a coat in a `Language`, English by default; `Language::ofLocale()`
reads `fr`, `fr_CA` or `fr-CA`. The blazon starts with a capital and has no closing full stop.

## English

The field first, then a comma and what lies on it. A charge's number, name and arrangement come before its tincture:

| Coat | Blazon |
|---|---|
| A plain field | `Azure` |
| Three charges | `Azure, three mullets in fess Or` |
| Charges on the ordinary | `Azure, on a fess Or three mullets gules` |
| … in the tincture of the field | `Azure, on a chief Or three fleurs-de-lis of the field` |
| Charges around the ordinary | `Azure, a fess Or between three mullets argent` |
| … in its tincture, written once | `Azure, a fess between two mullets Or` |
| A chief | `Azure, three mullets argent, a chief Or` / `Azure, three mullets and a chief Or` |
| A bordure | `Azure, a mullet Or within a bordure gules` |
| A mark of difference | `…, a label argent`, `…, overall a bendlet sable` |

Tinctures are lowercase but Or. A roundel is named by its tincture and written without it: bezant (Or), plate
(argent), torteau (gules), hurt (azure), pomme (vert), pellet (sable), golpe (purpure); a roundel of fur stays a
roundel. One charge takes « a », or « an » before a vowel.

## French

The field first, then each element introduced by « à » and its article, separated by commas; a divided field takes a
comma before the first. The tincture comes after the name and before the arrangement:

| Coat | Blazon |
|---|---|
| A plain field | `D’azur plein` |
| One charge | `D’azur à l’étoile d’or`, `D’azur au croissant d’argent`, `D’azur à la billette d’or` |
| Three charges | `D’azur à trois étoiles d’or rangées en fasce`, `… trois losanges d’argent posés en pal` |
| Charges on the ordinary | `D’azur à la fasce d’or chargée de trois étoiles de gueules`, `… au chevron d’or chargé d’un croissant` |
| … in the tincture of the field | `D’azur au chef d’or chargé de trois fleurs de lis du champ` |
| Charges around the ordinary | `D’azur à la fasce d’or accompagnée de trois étoiles d’argent` |
| … flanking a pale, in its tincture | `D’azur au pal d’or accosté de deux annelets du même` |
| A chief or a bordure | `D’azur à trois étoiles d’argent, au chef d’or` |
| A mark of difference | `…, au lambel d’argent`, `…, au bâton de sable brochant sur le tout` |

The grammar the writer follows:

- **Articles** contract: « au chef », « à la fasce », « à l’étoile »; a single charge on the field takes the definite
  article, a single charge on or around an ordinary « d’un » or « d’une ».
- **Elision**: « d’or », « d’argent », « d’azur », « d’hermine »; « de gueules », « de sinople », « de sable »,
  « de pourpre », « de vair ».
- **Agreement**: « chargé », « accompagné » and « accosté » agree with the ordinary — fasce, bande, barre, croix and
  bordure are feminine —; « rangés » and « posés » agree with the charges — étoile, macle, billette and fleur de lis
  are feminine.
- **Plurals**: tourteau, tourteaux; fleur de lis, fleurs de lis.
- A roundel is a **besant** on metal and a **tourteau** otherwise, and keeps its tincture.
- A plain field alone is **plein**; the bâton is **brochant sur le tout** when it lies over something.

## Reading a blazon

Reading a written blazon back into a `Coat` is not started.
