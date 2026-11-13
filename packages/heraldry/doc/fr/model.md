<!-- phil-dev source-sha256=55293957c20bb02e348ba1a05320234e2053987f2681113ec03773e2743e4a20 -->
# Le modèle

Des armoiries sont un `Coat` : un champ, et au plus un de chacun des autres éléments.

```php
use Tilttek\Heraldry\{Arrangement, Brisure, BrisureMark, Charge, ChargeGroup, Coat, Field, Ordinary, Partition, Piece, Tincture};

$coat = new Coat(
    Field::plain(Tincture::Azure),
    new Piece(Ordinary::Fess, Tincture::Or),
    new ChargeGroup(Charge::Mullet, Tincture::Argent, 3),
    new Brisure(BrisureMark::Label, Tincture::Gules),
);
```

## Le champ

`Field::plain()` prend un émail. `Field::divided()` prend une `Partition` et deux émaux différents :

| Partition | Anglais | Français | Le premier émail prend |
|---|---|---|---|
| `PerPale` | per pale | parti | la moitié dextre, à gauche pour qui regarde l'écu |
| `PerFess` | per fess | coupé | la moitié supérieure |
| `PerBend` | per bend | tranché | la moitié supérieure, au-dessus de la ligne qui part du chef dextre |
| `PerBendSinister` | per bend sinister | taillé | la moitié supérieure, au-dessus de la ligne qui part du chef senestre |
| `Quarterly` | quarterly | écartelé | les premier et quatrième quartiers |
| `PerSaltire` | per saltire | écartelé en sautoir | le chef et la pointe |

## Les émaux

Deux métaux (`Or`, `Argent`), cinq couleurs (`Gules`, `Azure`, `Vert`, `Sable`, `Purpure`) et deux fourrures (`Ermine`,
`Vair`). `Tincture::kind()` donne la famille, et `contrastsWith()` dit si l'un peut se poser sur l'autre ; voyez
[la règle de couleur](tincture-rule.md).

## Les pièces

Une `Piece` est un `Ordinary` dans son émail. Chaque pièce dit combien de meubles elle porte sur elle et combien
peuvent se tenir à côté :

| Pièce | Sur elle | À côté |
|---|---|---|
| `Chief` | 1, 2 ou 3, en rang | 1, 2 ou 3, dans le reste du champ |
| `Fess` | 1, 2 ou 3, en rang | 2 ou 3, autour d'elle |
| `Pale` | 1, 2 ou 3, en colonne | 2, de part et d'autre |
| `Bend`, `BendSinister` | 1, 2 ou 3, le long d'elle | 2, autour d'elle |
| `Chevron` | 1 ou 3 | 3, autour d'elle |
| `Cross`, `Saltire` | 1, au centre | aucun |
| `Bordure` | aucun | 1, 2 ou 3, à l'intérieur |

## Les meubles

Un `ChargeGroup` est de un à trois meubles d'une sorte et d'un émail : `Mullet`, `Crescent`, `Roundel`, `Annulet`,
`Lozenge`, `Mascle`, `Billet` ou `FleurDeLis`. Il se pose sur le champ, ou sur la pièce avec `onPiece: true`.

Sur le champ, sans pièce ou à côté d'un chef ou d'une bordure, deux ou trois meubles prennent un `Arrangement` :
`Usual` — deux au-dessus d'un pour trois, l'un au-dessus de l'autre pour deux —, `InFess` en rang, ou `InPale` en
colonne. Sur une pièce ou autour d'elle, les meubles la suivent et ne prennent aucune disposition.

## Les brisures

Une `Brisure` est une `BrisureMark` — un `Label` en travers du chef, ou un `Bendlet` sur le tout — dans son émail.

## Ce qu'un écu refuse

Les constructeurs lancent `Exception\InvalidCoat` pour un champ divisé d'un seul émail, un groupe de zéro meuble ou de
plus de trois, un meuble seul avec une disposition, des meubles sur une pièce que l'écu n'a pas, un nombre de meubles
que la pièce n'accepte pas, et une disposition pour des meubles qu'une pièce place. Un écu qui enfreint la règle de
couleur n'est pas refusé : de telles armes existent, et `TinctureRule` les distingue.
