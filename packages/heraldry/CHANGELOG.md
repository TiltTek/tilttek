# Changelog

All notable changes to `tilttek/heraldry` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-13

### Added

- The model of a coat of arms: `Coat`, a `Field` plain or divided by a `Partition` — per pale, per fess, per bend, per
  bend sinister, quarterly, per saltire —, the nine `Tincture`s and their `TinctureKind`, an `Ordinary` in its
  tincture as a `Piece` — chief, fess, pale, bend, bend sinister, chevron, cross, saltire, bordure —, a `ChargeGroup`
  of one to three `Charge`s — mullet, crescent, roundel, annulet, lozenge, mascle, billet, fleur-de-lis — on the field
  or on the ordinary, with an `Arrangement`, and a `Brisure` — label or bendlet; each ordinary says how many charges
  it carries and surrounds, and `InvalidCoat` refuses what the model cannot hold.
- `TinctureRule` and `Violation`: no metal on metal nor colour on colour, furs on either, nothing on its own tincture;
  bound on a plain field and on the ordinary, not on a divided field nor for a mark of difference; each fault in a
  sentence.
- `Generator\CoatGenerator` and `Generator\Seed`: arms drawn from 32 bytes of the system's secure source, written as
  64 hexadecimal digits, keying `Xoshiro256**` with their SHA-256 so that the same seed gives the same arms everywhere;
  every coat keeps the rule of tincture, and what lies side by side never shares a tincture; furs can be left out.
- `Blazon\Blazonry`, `Blazon\EnglishBlazon` and `Blazon\FrenchBlazon`: the blazon in English — tincture last, Or
  capitalised, a shared tincture written once, « of the field », roundels named by their tincture — and in French —
  articles contracted, « de » elided, participles agreeing with the ordinary and the charges, « du champ »,
  « du même », besants and tourteaux, « plein », « brochant sur le tout »; `Contract\BlazonWriter` to replace either.
- `Svg\SvgRenderer` and `Svg\Palette`: the arms drawn with the package's own shapes in four shield shapes, furs as
  patterns, an accessible image — `role="img"`, a title and the blazon as its description —, ids under a prefix given
  or derived from the arms, colours replaceable; the image passes `phil/svg-sanitizer` untouched.
- Fuzz targets for the generator and the drawing, and for the seeds; benchmarks of a generation, a blazon and a
  drawing.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
