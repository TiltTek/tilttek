<!-- phil-dev source-sha256=a60e5b5d553f4c04d70f89a1376cca08ce883aacf81763e3825a3410129609ab -->
# Historique et copies

## Versions

`Decks::save($deck, $at, $note = null)` enregistre le paquet comme sa version suivante, à partir de 1 : une
`DeckVersion` avec le paquet tel qu'il était, le moment en UTC et une note facultative d'au plus 500 caractères —
« après le régional ». Une version n'est jamais réécrite. Un paquet qui n'a pas changé depuis sa dernière version garde
cette version, de sorte qu'enregistrer à chaque changement d'un formulaire est sans danger.

Deux requêtes qui enregistrent le même paquet en même temps demandent à l'entrepôt d'ajouter la même version :
l'entrepôt garde la première, et la seconde lève `DeckConflict`, pour que l'application relise le paquet.

| Méthode | Donne |
|---|---|
| `latest($deck)` | La dernière version |
| `version($deck, $version)` | Une version donnée |
| `history($deck)` | Chaque version, de la première à la dernière |

Un paquet sans version enregistrée, ou sans la version demandée, lève `UnknownDeck`.

## Différences

`Decks::diff($deck, $from, $to)` donne le `DeckDiff` entre deux versions, dans un ordre ou dans l'autre ;
`DeckDiff::between()` compare deux paquets quelconques. Il liste les cartes ajoutées, retirées et dont la quantité a
changé, et si le nom ou le format a changé ; `lines()` les écrit pour que les gens les lisent :

```text
+2 ember-fox
-1 fox-den
storm-call: 2 → 1
Name: "Foxes" → "Foxes II"
```

## Copies

`Decks::copy($deck, $as, $name, $at, $version = null)` copie une version d'un paquet — sa dernière par défaut — comme
première version d'un nouveau paquet d'identifiant `$as`, dont `copiedFrom` est une `DeckReference` vers le paquet et la
version d'origine. La copie garde le format et les cartes de l'original ; l'original n'est jamais changé. Un
identifiant déjà utilisé lève `DeckConflict`.

Le lien reste avec la copie et ses versions suivantes, de sorte qu'une application peut montrer d'où vient un paquet et
compter les copies d'un paquet publié.
