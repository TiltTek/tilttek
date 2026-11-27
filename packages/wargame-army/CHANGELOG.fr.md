<!-- phil-dev source-sha256=b2502218f5d09c68c1f79cc3b03ed8f0a810b12fb17e9d927c591b9c425997fa -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/wargame-army` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `Catalogue`, `UnitEntry`, `OptionGroup`, `Option`, `DetachmentType` et `Slot` : les données d'un jeu à part du
  moteur — jusqu'à 2 000 unités avec leurs catégories, leurs points, leurs figurines et leurs groupes d'options, jusqu'à
  50 types de détachements avec leurs emplacements par catégorie, les détachements que prend une armée et jusqu'à 200
  contraintes déclarées —, lues depuis et écrites vers des tableaux et du JSON d'au plus 4 Mio, chaque défaut une
  `InvalidCatalogue`.
- `ArmyList`, `Detachment` et `UnitSelection` : une liste avec son catalogue et sa version, sa limite de points et
  jusqu'à 20 détachements d'au plus 200 unités, modifiée par de nouvelles listes, lue depuis et écrite vers des tableaux
  et du JSON, chaque défaut une `InvalidArmyList`.
- `Costing` : les points de chaque unité — les siens, ceux de ses figurines au-delà du minimum et de ses options, pour
  l'unité ou pour chaque figurine —, de chaque détachement et de la liste, et le nombre et les points des unités que
  prend un sélecteur.
- `ArmyValidator`, `Validation`, `Violation` et `Rule` : une liste vérifiée contre son catalogue une règle déclarée à la
  fois — catalogue et version, détachements, types, emplacements, figurines, options, groupes d'options, limite de
  points, contraintes —, chaque violation renvoyée avec une phrase lisible et les valeurs à partir desquelles elle a été
  construite.
- `Constraint\UnitCount`, `Constraint\PointsShare`, `Constraint\Selector`, `Constraint\Scope`, `Contract\Constraint` et
  `Contract\DeclaredConstraint` : des nombres d'unités dans l'armée ou dans chaque détachement, des parts de la limite
  de points comparées exactement, et les propres contraintes de l'application ajoutées au validateur.
- `Export\TextExport` et `Export\HtmlExport` : une liste en texte brut et en fragment de HTML échappé, avec ses
  violations quand une validation est donnée.
- `Contract\CatalogueProvider` et `Catalog\InMemoryCatalogueProvider` : le port vers les catalogues de l'application et
  son adaptateur en mémoire.
- `Identifiers` : les formes des codes, des versions, des identifiants et des noms.
- Aucun catalogue d'éditeur n'est fourni ni importé : l'application fournit le sien, comme le dit
  `doc/catalogues-and-licences.md`.
- Les cibles de fuzzing d'un catalogue et d'une liste, et les bancs d'essai de la plus grande liste.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
