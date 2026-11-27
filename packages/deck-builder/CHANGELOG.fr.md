<!-- phil-dev source-sha256=537faee6c984dad93d59a2f7dc91c1c51adf752b8bfbc1059f88080d0428d0db -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/deck-builder` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `Format` : les règles déclarées d'un format — le plus petit paquet, de 0 à 100 000 cartes, le plus grand ou aucun, les
  exemplaires de chaque carte, de 1 à 999, jusqu'à 10 000 cartes interdites et 10 000 restreintes, et jusqu'à 100 listes
  taboues datées —, lues depuis et écrites vers des tableaux et du JSON d'au plus 1 Mio, chaque défaut une
  `InvalidDeck`.
- `TabooList` : des cartes interdites ou restreintes à partir d'un jour, la plus récente qui s'applique un jour donné
  remplaçant les précédentes et s'ajoutant aux propres listes du format, le jour pris dans le fuseau horaire du moment
  donné.
- `Ruling` : les règles d'un format un jour donné — ce qui interdit une carte, ce qui la restreint, et les exemplaires
  permis, une restriction ne relevant jamais une limite.
- `Card` : une carte telle que la donne le catalogue de l'application, avec son propre nombre d'exemplaires quand il
  diffère de celui du format.
- `Deck` et `DeckReference` : jusqu'à 1 000 cartes distinctes de 1 à 999 exemplaires dans l'ordre où elles ont été
  ajoutées, modifiées par de nouveaux paquets, réduites à une collection, copiées avec un lien vers la version de
  l'original, lues depuis et écrites vers des tableaux et du JSON.
- `DeckValidator`, `Validation`, `Violation` et `ViolationType` : un paquet vérifié contre son format un jour donné, et
  contre une collection, chaque violation renvoyée avec une phrase lisible et les valeurs à partir desquelles elle a été
  construite.
- `Decks`, `DeckVersion` et `DeckDiff` : des versions enregistrées à partir de 1 et jamais réécrites, un paquet inchangé
  gardant sa dernière version, une version enregistrée deux fois en même temps refusée avec `DeckConflict`,
  l'historique, les différences entre deux versions, et des copies comme première version d'un nouveau paquet liée à
  l'original.
- `Contract\CardCatalog`, `Contract\FormatCatalog`, `Contract\OwnedCards`, `Contract\DeckStore`,
  `Catalog\InMemoryCardCatalog`, `Catalog\InMemoryFormatCatalog`, `Catalog\OwnedCardList` et `Store\InMemoryDeckStore` :
  les ports vers les cartes, les formats, les collections et les versions de l'application, et leurs adaptateurs en
  mémoire.
- `Identifiers` : les formes des codes, des identifiants et des noms.
- Aucun catalogue d'éditeur n'est fourni ni importé : l'application fournit ses propres cartes et formats, comme le dit
  `doc/catalogues-and-licences.md`.
- Les cibles de fuzzing d'un format et d'un paquet, et les bancs d'essai du plus grand paquet.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
