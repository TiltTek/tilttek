<!-- phil-dev source-sha256=e2d11a8aa33719c096004a01c0ca20b8e6ab5c348fca26cbaf9c5403a8451cbe -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/dice-jail` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-24

### Modifié

- `tilttek/dice` 0.3 requis : 0.2 n'est plus accepté.

## 0.1.0-alpha - 2026-11-20

### Ajouté

- `Jail\Warden`, `Jail\Inmate`, `Jail\JailEvent`, `Jail\EventKind` et `Jail\Evidence` : le dé d'un membre mis en prison
  pour une peine qui se termine d'elle-même ou jusqu'à sa libération, une seule fois à la fois, au plus 1 000 par
  gardien ; les libérations ; les dés en prison dans l'ordre où ils y sont entrés ; l'historique de chaque dé ; les
  statistiques montrées gardées avec la mise en prison ; un gardien refusé quand il est écrit comme une adresse
  courriel.
- `Statistics\GoodnessOfFit`, `Statistics\FitResult`, `Statistics\FaceCount` et `Statistics\Verdict` : le test du khi
  carré de Pearson d'un dé contre un dé équilibré, fiable à partir de cinq lancers attendus par face, sa valeur p tirée
  de la fonction gamma incomplète régularisée avec Γ exacte, et quatre verdicts — pas assez de lancers, rien
  d'inhabituel, plutôt inhabituel sous 1 %, très inhabituel sous 0,1 % —, dont aucun ne dit qu'un dé est pipé ; les
  moyennes observée et attendue.
- `Statistics\Tally` et `Statistics\ExpectedFaces` : les faces comptées, à partir de valeurs ou des dés lancés de
  `tilttek/dice`, jusqu'à 1 000 000 de lancers ; des dés équilibrés à partir d'une plage, ou d'une distribution exacte
  de `tilttek/dice-probability`, les distributions estimées et coupées refusées ; le minimum de lancers en lancers
  entiers.
- `Statistics\Explanation`, `Statistics\Language` et `Statistics\Origin` : le verdict en anglais et en français simples,
  avec la typographie de chaque langue, le lancer moyen, le conseil d'examiner un dé physique ou de tester de nouveaux
  lancers, et pour les dés numériques le rappel que le générateur sert tous les dés de la même façon.
- `Statistics\DieAssessor` et `Contract\RollSource`, avec `Store\InMemoryRollSource` : le test à partir des lancers
  enregistrés, depuis un moment.
- `Contract\JailStore`, avec `Store\InMemoryJailStore` : les historiques, chaque événement à sa position, une écriture
  concurrente refusée avec `Exception\JailConflict`.
- Des exceptions sous une seule interface `DiceJailException` : `InvalidRolls`, `InvalidJailEntry`, `JailRefused`,
  `JailConflict`.
- Une cible de fuzzing pour le test, et des bancs d'essai d'un d20 jugé et d'un d100 testé.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
