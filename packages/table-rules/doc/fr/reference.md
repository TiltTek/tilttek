<!-- phil-dev source-sha256=808a7d198a2479d2643cea6b6a07539b8435d7415fa28cac0549ac007930e7d1 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Règles et versions

| Type | Rôle |
|---|---|
| `Rule` | Une règle maison : identifiant, titre, texte et catégorie. |
| `RulebookVersion` | Une version publiée : numéro, règles, variantes, système, note, auteur, moment et si elle exige une acceptation. |
| `Rulebook` | Où en sont les règles d'une table : la version courante, la base de référence, la dernière décision de chaque joueur et la révision. |
| `Acceptance` | La dernière décision d'un joueur : la version, acceptée ou refusée, et quand. |
| `Decision` | Acceptée ou refusée. |
| `AcceptanceState` | En attente, acceptée, périmée ou refusée. |
| `TableRules` | Le service : publier, accepter, refuser, oublier, version, état, liste et comparaison. |

## Ce qui a changé

| Type | Rôle |
|---|---|
| `Diff\RulebookDiffer` | Compare deux versions. |
| `Diff\RulebookDiff` | Les règles ajoutées, retirées, modifiées et déplacées, les variantes choisies et abandonnées, le système avant et après. |
| `Diff\RuleChange` | Une règle des deux versions avec un autre titre, texte ou catégorie, et les lignes de son texte. |
| `Diff\LineChange` | Une ligne d'un texte, gardée, retirée ou ajoutée. |
| `Diff\LineKind` | Gardée, retirée ou ajoutée. |

## Vues

| Type | Rôle |
|---|---|
| `View\PlayerStatus` | Où en est un joueur, et ce qui a changé depuis la version à laquelle il a répondu. |
| `View\RosterView` | Où en sont les joueurs nommés, et combien sont dans chaque état. |

## Ports et magasins

| Type | Rôle |
|---|---|
| `Contract\RulebookStore` | Garde le livre de règles de chaque table à une révision, et chaque version publiée. |
| `Contract\TableRulesObserver` | Informé de chaque fait une fois le livre de règles gardé. |
| `Contract\VariantCatalogue` | Dit si un système de jeu offre une variante. |
| `Catalogue\SystemVariantCatalogue` | Le catalogue des variantes de règles des systèmes de `tilttek/rpg-systems`. |
| `Store\InMemoryRulebookStore` | Les livres de règles en mémoire, pour les tests. |

## Événements

| Type | Rôle |
|---|---|
| `Event\RulesEvent` | Un fait des règles d'une table, par identifiants seulement. |
| `Event\RulebookPublished` | Une nouvelle version a été publiée. |
| `Event\RulesAccepted` | Un joueur a accepté la version qui fait foi. |
| `Event\RulesDeclined` | Un joueur a refusé la version qui fait foi. |
| `Event\PlayerForgotten` | La décision d'un joueur a été oubliée. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\TableRulesException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidRules` | Une valeur hors de sa forme ou de ses bornes. |
| `Exception\RulesRefused` | Un changement non permis sur le livre de règles dans son état. |
| `Exception\RulesNotFound` | Aucune règle publiée, ou aucune version de ce numéro. |
| `Exception\RulesConflict` | Le livre de règles a changé depuis sa lecture. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme de chaque identifiant. |
| `Internal\Text` | La forme des titres, des textes et des notes, et leur longueur. |
| `Internal\Lcs` | La plus longue sous-suite commune de deux listes de chaînes. |
