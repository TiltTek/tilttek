<!-- phil-dev source-sha256=cfd18fdd420f197e92bf80edc8da32b3c4acbac2d13ded2d5196f3ea09dfcd6b -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/score-sheets` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `SheetDefinition`, `Category` et `Tiebreaker` : la feuille d'un jeu déclarée comme données — de 1 à 64 catégories,
  chacune entrée entre ses bornes ou calculée à partir des précédentes, comptée dans le total ou non, jusqu'à 8 critères
  de départage et la façon dont les équipes se combinent —, lue depuis du JSON d'au plus 64 Kio et réécrite.
- Les opérations `Sum`, `Product`, `Majority` — des points par place, les égalités partageant leur place — et `Bonus`,
  et `Operations`, qui les relit ; des résultats entiers qui refusent de déborder.
- `Entries` et `Team` : de 1 à 100 participants, jusqu'à 50 équipes, et des valeurs entrées, corrigées ou effacées.
- `Scoring\Scorer` : le tableau des pointages calculé à chaque entrée — les totaux des catégories qui comptent, les
  rangs par total puis par critères de départage, des rangs partagés, les pointages des équipes par somme ou par
  meilleur — avec une `Step` explicative par catégorie.
- `ScoreKeeper`, `MatchSheet` et `SheetStatus` : la feuille d'une partie préparée hors de la vue des joueurs, publiée,
  remplie contre sa version, close, et rouverte pour corriger un pointage entré trop tôt, chaque clôture formant une
  nouvelle révision.
- `MatchResult`, `Placement` et `Message\MatchResultRecorded` : le résultat neutre d'une partie close pour un journal de
  parties ou une cote, et son message avec la partie et la révision seulement.
- `BlankSheet` : la feuille vierge à imprimer, en données neutres.
- `Contract\MatchSheetStore` et `Store\InMemoryMatchSheetStore` : le port du stockage, écrit par-dessus la version
  précédente seulement, et le stockage en mémoire pour les tests.
- Des exceptions sous une seule interface `ScoreSheetsException` : `InvalidSheet`, `InvalidEntry`, `IllegalTransition`,
  `UnknownMatch` et `ConcurrentChange`.
- Des tests de propriétés sur des entrées aléatoires et une cible de fuzzing des déclarations.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
