# The model

A coat of arms is a `Coat`: a field, and at most one of each of the rest.

```php
use Tilttek\Heraldry\{Arrangement, Brisure, BrisureMark, Charge, ChargeGroup, Coat, Field, Ordinary, Partition, Piece, Tincture};

$coat = new Coat(
    Field::plain(Tincture::Azure),
    new Piece(Ordinary::Fess, Tincture::Or),
    new ChargeGroup(Charge::Mullet, Tincture::Argent, 3),
    new Brisure(BrisureMark::Label, Tincture::Gules),
);
```

## The field

`Field::plain()` takes one tincture. `Field::divided()` takes a `Partition` and two different tinctures:

| Partition | English | French | The first tincture takes |
|---|---|---|---|
| `PerPale` | per pale | parti | the dexter half, on the left as one looks at the shield |
| `PerFess` | per fess | coupé | the upper half |
| `PerBend` | per bend | tranché | the upper half, above the line from the dexter chief |
| `PerBendSinister` | per bend sinister | taillé | the upper half, above the line from the sinister chief |
| `Quarterly` | quarterly | écartelé | the first and fourth quarters |
| `PerSaltire` | per saltire | écartelé en sautoir | the chief and the base |

## Tinctures

Two metals (`Or`, `Argent`), five colours (`Gules`, `Azure`, `Vert`, `Sable`, `Purpure`) and two furs (`Ermine`,
`Vair`). `Tincture::kind()` gives the family, and `contrastsWith()` says whether one may lie on another; see
[the rule of tincture](tincture-rule.md).

## Ordinaries

A `Piece` is an `Ordinary` in its tincture. Each ordinary says how many charges it carries on itself and how many may
lie beside it:

| Ordinary | On it | Beside it |
|---|---|---|
| `Chief` | 1, 2 or 3, in a row | 1, 2 or 3, in the rest of the field |
| `Fess` | 1, 2 or 3, in a row | 2 or 3, around it |
| `Pale` | 1, 2 or 3, in a column | 2, flanking it |
| `Bend`, `BendSinister` | 1, 2 or 3, along it | 2, around it |
| `Chevron` | 1 or 3 | 3, around it |
| `Cross`, `Saltire` | 1, at the centre | none |
| `Bordure` | none | 1, 2 or 3, within it |

## Charges

A `ChargeGroup` is one to three charges of one kind and one tincture: `Mullet`, `Crescent`, `Roundel`, `Annulet`,
`Lozenge`, `Mascle`, `Billet` or `FleurDeLis`. It lies on the field, or on the ordinary with `onPiece: true`.

On the field, with no ordinary or beside a chief or a bordure, two or three charges take an `Arrangement`: `Usual` —
two above one for three, one above the other for two —, `InFess` in a row, or `InPale` in a column. On or around an
ordinary, the charges follow it and take no arrangement.

## Marks of difference

A `Brisure` is a `BrisureMark` — a `Label` across the chief, or a `Bendlet` over everything — in its tincture.

## What a coat refuses

The constructors throw `Exception\InvalidCoat` for a divided field of one tincture, a group of no charge or of more
than three, a single charge with an arrangement, charges on an ordinary the coat does not have, a number of charges the
ordinary does not take, and an arrangement for charges an ordinary places. A coat that breaks the rule of tincture is
not refused: such arms exist, and `TinctureRule` tells them apart.
