<!-- phil-dev source-sha256=f364b314258497726a861822e58364022d02e5d078c75ba44f2479f8700b4926 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/reference-tables` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `ReferenceTable` et `Lookup` : une table d'un système de jeu avec 1 à 32 colonnes et 1 à 1 000 rangées, consultée par
  clé ou par plage, son nom et sa description par locale, sa source et sa licence en `Provenance` de
  `tilttek/rpg-systems`, son système, sa catégorie et sa page ; un dé d'une seule instruction de `tilttek/dice` sur une
  table consultée par plage, gardé dans sa notation canonique ; des clés une fois, des plages sans chevauchement.
- `ReferenceTable::row()`, `hasRow()`, `lookup()`, `position()`, `bounds()` et `gaps()` : une rangée par sa clé, la
  rangée dont la plage couvre une valeur, par dichotomie sur les plages triées, sa position, les valeurs que la table
  couvre et celles qu'elle laisse de côté.
- `Column`, `ColumnKind` et `Cell` : des colonnes de textes par locale, d'entiers jusqu'à un milliard dans les deux
  sens, ou d'expressions de dés gardées dans la notation canonique de `tilttek/dice`, chaque cellule vérifiée par
  rapport à sa colonne.
- `Row` : une clé ou une plage, jamais les deux, et des cellules par colonne, n'importe laquelle pouvant être omise.
- `Range` : `12`, `3–5`, `21+`, `≤0`, et `96–00` sur un d100 où `00` vaut 100, à ±1 000 000 près, lue sur au plus 32
  octets et réécrite.
- `TableCatalogue` : jusqu'à 2 000 tables, chaque clé une fois, par système et par catégorie, avec les catégories dans
  l'ordre et les sources de chaque table, ou de celles qu'une page montre, chacune une fois pour l'attribution.
- `Rolling\TableRoller` et `Rolling\TableRoll` : une table lancée avec son dé par `tilttek/dice` sur le `Randomizer` de
  l'appelant, avec un modificateur, un total au-delà de la table ramené à sa première ou à sa dernière rangée sur
  demande, un total dans un trou refusé ; un dé lancé à la table saisi ; les dés d'une cellule lancés.
- `Contract\TableObserver` et `Event\TableRolled` : chaque jet annoncé par la clé de la table, la position de la rangée,
  le total et s'il a été saisi.
- `Search\TableSearch` et `Search\SearchHit` : une recherche d'au plus 100 caractères trouvée dans les noms, les
  descriptions, les clés de rangées et les cellules de texte d'un catalogue, dans une locale, sans tenir compte de la
  casse ni des accents des lettres latines, pour au plus 100 résultats, dans un seul système sur demande.
- `Format\TableReader` et `Format\Syntax` : des documents du format `tilttek-reference-tables/1` en YAML ou en JSON,
  jusqu'à 2 Mio, avec une locale, un système et une source par défaut, chaque champ inconnu refusé et chaque refus à sa
  place.
- Les exceptions `InvalidTable`, `InvalidDocument`, `UnknownTable`, `UnknownRow` et `LookupRefused`, sous
  `ReferenceTablesException`.
- Le document d'exemple `examples/lanterns.yaml`, les cibles de fuzzing des documents et des plages, et les bancs
  d'essai des consultations, des jets, des recherches et de la lecture.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
