<!-- phil-dev source-sha256=d3f8f117f1bd5ec8024c42c62bcf916a2a006270ad3ec00ffb8bd4584a2590ae -->
# Rencontres et participants

## Une rencontre

`EncounterPlanner::prepare()` ouvre une rencontre : son identifiant, son meneur de jeu, la clé de son système de jeu,
un titre facultatif d'une ligne d'au plus 200 caractères, et des participants, ou aucun pour l'instant. Quand des
`Rulebooks` sont donnés, un système pour lequel ils ne déclarent aucune règle est refusé avec `UnknownSystem`. Un
identifiant déjà gardé est refusé avec `EncounterConflict`.

Les identifiants — d'une rencontre, d'un membre, d'un participant, d'une fiche, d'un système, d'un palier — comptent de
1 à 128 lettres, chiffres, `_`, `.`, `:` ou `-`, commencent par une lettre, et ne sont jamais un nom ni une adresse.
L'application garde les noms, les descriptions et les statistiques dans ses propres fiches.

Seul le meneur de jeu modifie une rencontre : chaque changement nomme qui appelle, et l'appel de toute autre personne
est refusé avec `EncounterRefused`. L'application dit qui appelle ; le service lui fait confiance.

## Participants

Un `Participant` est une ligne de la rencontre :

| Valeur | Ce que c'est |
|---|---|
| `id` | Son identifiant, unique dans la rencontre, d'au plus 124 caractères. |
| `kind` | `Kind::Character`, `Kind::Adversary` ou `Kind::Ally`. |
| `rating` | Son niveau ou son défi tel que le système de jeu l'écrit : `3`, `1/4`, `-1`, `12+` ; au plus 16 lettres, chiffres, `/`, `.`, `+` ou `-`. |
| `controller` | Le membre qui le joue : un joueur pour son personnage, le meneur de jeu pour les autres. |
| `quantity` | De 1 à 50 copies d'un même modèle ; un personnage est toujours seul. |
| `hidden` | Si les joueurs ne le voient pas. |
| `initiativeBonus` | Ce qu'il ajoute à son initiative, de -99 à 99, ou null quand le système n'ajoute rien. |
| `ref` | L'identifiant de la fiche de l'application — une feuille de personnage, une fiche de créature — ou null. |

Les copies d'un participant sont chacune un combattant : `wolves` de trois copies donne `wolves.1`, `wolves.2` et
`wolves.3` ; un participant d'une seule copie est son propre combattant. Une rencontre contient au plus 100 participants
et 100 combattants, chaque copie comptée ; un identifiant de combattant qui revient — `wolves.2` nommé comme un autre
participant — est refusé.

## Modifier une rencontre

| Appel | Ce qu'il fait |
|---|---|
| `add()` | Ajoute un participant en dernier, ou juste `before` ou `after` un autre. |
| `change()` | Remplace un participant par celui du même identifiant, à sa place. |
| `move()` | Déplace un participant juste `before` ou `after` un autre ; un seul des deux est nommé. |
| `remove()` | Retire un participant. |
| `retitle()` | Donne un autre titre à la rencontre, ou aucun avec null. |

L'ordre est celui que fixe le meneur de jeu en plaçant un participant à côté d'un autre : aucun numéro à garder en
séquence. Un changement qui ne change rien — le même titre, le même participant, un déplacement qui garde l'ordre — ne
garde rien et n'annonce rien. Nommer un participant que la rencontre ne contient pas est refusé avec
`EncounterRefused` ; nommer les deux côtés à la fois est refusé avec `InvalidEncounter`.

Chaque changement lit la rencontre, la modifie, la garde à la révision suivante, puis en informe l'observateur. Quand la
rencontre a changé depuis sa lecture, le magasin la refuse avec `EncounterConflict`, et rien n'est gardé.

## Évaluer

`assess()` donne la difficulté de la rencontre selon les règles de son système de jeu, comme le décrit
[Difficulté](difficulty.md). Une application qui prévisualise un participant avant de l'ajouter interroge directement
les règles : `$rulebooks->for($system)->assess([...$encounter->participants, $candidate])`.

## Lancer

`launch()` garde la rencontre lancée, puis remet sa liste au suivi d'initiative, comme le décrit
[Initiative](initiative.md), et annonce `EncounterLaunched`. Une rencontre lancée ne change plus : chaque changement, et
un second lancement, est refusé avec `EncounterRefused`. On peut toujours la lire, l'évaluer et la donner comme liste.
Une rencontre sans participant n'est pas lancée.

## Oublier

`forget()` retire la rencontre du magasin, lancée ou non, et annonce `EncounterForgotten`.
