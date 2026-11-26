<!-- phil-dev source-sha256=e6b0d888cb72722fff965e392527773c3e36e0a87f88af44763b508ea573329d -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Valeur par défaut |
|---|---|---|
| `Contract\HandoutStore` | Où sont gardés les documents, les divulgations, les révélations planifiées et les vues | `Store\InMemoryHandoutStore` dans les tests |
| `Contract\HandoutObserver` | Que faire d'un changement d'un document | Aucune |
| `Limits` | Ses bornes sur les documents par table, les pièces jointes par document, les audiences par document, les révélations par déclenchement | 200, 10, 50 et 100 |
| `MediaTypes` | Les types de média que peuvent avoir les pièces jointes | Images courantes, PDF, sons, vidéos et texte brut ; pas de SVG |
| `Psr\Clock\ClockInterface` | Le moment de chaque acte, et ce qui est dû | — |

## Écrire un dépôt de documents

Un `HandoutStore` nomme les documents, les tables et les joueurs par leurs références écrites `genre:identifiant`, et
une audience par sa clé : `*` pour toute la table, ou la référence d'un joueur. Il garde :

- les documents, par référence — `handout()`, `saveHandout()`, qui remplace le précédent, `removeHandout()`, qui
  supprime avec le document ses divulgations, ses révélations planifiées et ses vues, `countHandouts()`, et
  `handouts($table)`, le plus ancien créé d'abord ;
- les divulgations, une par document et par clé — `disclosure()`, `saveDisclosure()`, `removeDisclosure()`,
  `disclosures($handout)`, la plus anciennement révélée d'abord, et `disclosuresFor($table, $player)`, les divulgations
  des documents de la table sous `*` ou sous la clé du joueur, la plus anciennement révélée d'abord ;
- les révélations planifiées, une par document et par clé — `staged()`, `saveStaged()`, `removeStaged()`,
  `stagedOf($handout)`, la plus anciennement planifiée d'abord, `due($until, $limit)`, les révélations de toutes les
  tables planifiées à un moment au plus égal à `$until`, le plus ancien moment d'abord, et
  `onCue($table, $cue, $limit)`, la plus anciennement planifiée d'abord ;
- les vues, une par document et par joueur — `sighting()`, `saveSighting()`, et `sightings($handout)`, le premier à
  l'avoir vu d'abord ;
- `forgetPlayer($player)`, qui supprime les entrées du joueur dans toutes les tables et donne les références des
  documents qui en contenaient, dans l'ordre des octets.

Les moments à égalité sont lus dans l'ordre des octets de la référence du document, puis de la clé ou du joueur. Un
dépôt garde `due()` rapide avec un index sur le moment des révélations planifiées, et `disclosuresFor()` avec un index
sur la table et la clé.

La bibliothèque vérifie les limites avant d'écrire : une application qui sert des requêtes concurrentes exécute chaque
acte de `Handouts` dans une transaction, ou sous un verrou sur le document, pour que deux révélations ne dépassent
jamais la limite ensemble, et exécute `releaseDue()` depuis un seul planificateur, ou sous un verrou, pour qu'une
révélation planifiée soit déclenchée une seule fois.

## Observateurs

Un `HandoutObserver` reçoit un `HandoutChanged` après chaque acte, une fois que le dépôt l'a écrit. L'événement ne
contient que des identifiants — le document, sa table, le joueur concerné ou aucun pour toute la table, la référence
qui agit — avec le `Change` et le moment ; les titres, les textes et les fichiers n'y sont jamais. Un observateur relaie
l'événement — une poussée vers les écrans des joueurs, un message sur un bus — et ne garde aucun état entre deux
appels. Un écran qui le reçoit lit le document par l'application, qui vérifie `canSee()`.

| `Change` | Joueur |
|---|---|
| `Created`, `Edited`, `Removed` | — |
| `Revealed`, `Concealed`, `Staged`, `Unstaged` | Le joueur, ou aucun pour toute la table |
| `Seen`, `Forgotten` | Le joueur |

`Revealed`, `Concealed`, `Staged` et `Unstaged` sont communiqués une fois par joueur de l'audience, ou une fois pour
toute la table.
