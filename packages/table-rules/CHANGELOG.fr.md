<!-- phil-dev source-sha256=0d09bfd2cc2198ab6fb0a5871f3a568ed9e4364fd33d9e64f9b88fb483caf3f2 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/table-rules` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Rule` : une règle maison d'une table, son identifiant gardé d'une version à l'autre, un titre d'une ligne d'au plus
  200 caractères, un texte brut d'au plus 10 000 caractères sur 100 lignes, ses sauts de ligne et ses blancs normalisés,
  et une catégorie facultative ; en UTF-8 valide, sans caractères de contrôle ni caractères qui réordonnent le texte
  affiché.
- `RulebookVersion` : une version publiée, numérotée de 1 à 10 000, d'au plus 200 règles dans l'ordre de lecture,
  100 000 caractères de texte en tout, jusqu'à 100 variantes du système de jeu gardées triées, le système, une note d'au
  plus 2 000 caractères, son auteur et son moment, et si elle exige une acceptation ; la première en exige toujours une.
- `Rulebook`, `Acceptance`, `Decision` et `AcceptanceState` : la version courante, la dernière version qui a exigé une
  acceptation, et la dernière décision d'au plus 500 joueurs, gardées à une révision ; chaque joueur en attente, ayant
  accepté, ayant refusé, ou dépassé quand les règles ont changé depuis la version acceptée.
- `TableRules::publish()` : la version suivante des règles d'une table, en vigueur dès maintenant, refusée quand elle
  contient les règles, les variantes et le système déjà en vigueur ; les variantes vérifiées par rapport à un
  `Contract\VariantCatalogue` quand il y en a un, tel que `Catalogue\SystemVariantCatalogue`, les variantes de règles
  des systèmes de `tilttek/rpg-systems`.
- `TableRules::accept()`, `decline()` et `forget()` : la réponse d'un joueur à la version qu'il a lue, refusée dès
  qu'une autre est en vigueur, une réponse donnée de nouveau ne changeant rien ; la décision d'un joueur oubliée.
- `TableRules::version()` et `diff()` : une version par son numéro, et ce qui a changé entre deux versions.
- `Diff\RulebookDiffer`, `Diff\RulebookDiff`, `Diff\RuleChange`, `Diff\LineChange` et `Diff\LineKind` : les règles
  ajoutées, retirées, déplacées et modifiées, ligne par ligne dans un texte, les variantes choisies et abandonnées, et
  le système avant et après, par la plus longue sous-séquence commune, bornée par les bornes d'une version.
- `TableRules::status()` et `roster()`, avec `View\PlayerStatus` et `View\RosterView` : où en est un joueur et ce qui a
  changé depuis la version à laquelle il a répondu ; où en sont jusqu'à 500 joueurs et combien dans chaque état, en
  données simples.
- Les événements `RulebookPublished`, `RulesAccepted`, `RulesDeclined` et `PlayerForgotten`, par identifiants seulement,
  annoncés une fois à un `Contract\TableRulesObserver`.
- `Contract\RulebookStore`, qui garde le recueil de règles à une révision avec chaque version publiée, et
  `Store\InMemoryRulebookStore` pour les tests.
- Les exceptions `InvalidRules`, `RulesRefused`, `RulesNotFound` et `RulesConflict`, sous `TableRulesException`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
