<!-- phil-dev source-sha256=8f42a99c392b3da4394198f62cc6694c4f7a34d81cfc12de2825ba2bddd5deb9 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/geek-dates` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Entry` et `Status` : une date de la culture geek avec un identifiant stable, des noms en anglais, en français et dans
  jusqu'à 32 locales, une règle de `phil/almanac`, une source https lue par `phil/url` et gardée sous sa forme
  canonique, une communauté, le jour de sa dernière vérification, jusqu'à 16 étiquettes, de 1 à 366 jours, et si elle
  est vérifiée ou encore à vérifier ; son nom dans la locale la plus proche et sa définition d'almanach.
- `EntryReader` : des entrées lues à partir de tableaux, la forme des données fournies et des propres entrées d'une
  application, tout champ inconnu refusé.
- `Catalog`, `Filter` et `Occurrence` : les entrées dans un seul fuseau horaire, les dates d'une année, d'une période ou
  d'un jour, filtrées par étiquette, communauté ou vérification, chacune avec son entrée et sa source ; les propres
  entrées d'une application ajoutées à côté de celles qui sont fournies, un identifiant en double refusé ; les années
  1000 à 3000.
- `ShippedData`, `data/dates.php` et `data/VERSION` : 18 entrées — journées des sciences, des mathématiques, de l'espace
  et de l'informatique, journées des lecteurs de Tolkien et de Douglas Adams —, chacune vérifiée contre sa source.
- `DataCheck` : chaque problème d'un ensemble de lignes, une règle qui ne se lit pas, une source ou une date de
  vérification manquante, un identifiant en double.
- Des exceptions sous une seule interface `GeekDatesException` : `InvalidEntry` et `InvalidQuery`.
- Des bancs d'essai de la construction du catalogue fourni et des dates d'un jour et d'une année.
- Le squelette du paquet : `composer.json`, `phil-dev.php` et `composer-dependency-analyser.php`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
