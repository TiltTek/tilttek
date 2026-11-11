# Changelog

All notable changes to `tilttek/loot-tables` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-11

### Added

- `LootEntry`: an entry with its key, its name of up to 1000 characters, the path or URL of its image and its slot.
- `LootTable`: 2 to 1000 entries rolled with a die of as many sides, the entry of face 1 first; `ofTexts()` for a list
  of texts.
- `LootBox`: the 1 to 10 tables a product rolls, in order, each with its own die; `single()`, `dice()` and the
  `tilttek/dice` pool of the box.
- `LootCatalog`: the tables and boxes of an application by key, every box rolling tables of the catalogue and writing
  its draws within 1000 characters; `CatalogReader` reads it from decoded YAML or JSON, naming the path of a value it
  refuses.
- `PendingDraw`: a secret seed drawn from a secure source, its commitment published first, and a nonce; restored from
  its stored seed.
- `LootDrawer`: the reveal of a box from a pending draw and the buyer's client seed of 1 to 256 bytes of UTF-8 text, given after the
  commitment, through the verifiable rolls of `tilttek/dice`, and the verification of a published proof;
  `RevealedDraw` and its proof.
- `LootResult`, `Pick` and `DrawSource`: one face and one entry per table, verifiable or entered from physical dice.
- `ShopCode`: one character per die, the digits then the hexadecimal continued to `K` for a d20, written and read back
  in either case.
- `DrawText`: the faces joined by `-` and the names of the entries, written and read back from the faces alone.
- `LootException`, `InvalidLoot`, `UnknownLoot` and `InvalidCode`.
- Fuzz targets for the shop codes and the draw texts, and benchmarks of the reveal, the verification and the codes.
- Documentation: installation, tables and boxes, drawing, codes, exceptions, extension points, testing and
  reference.
- Community files: contribution guide, code of conduct, security policy and MIT licence.
