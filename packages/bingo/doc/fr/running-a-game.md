<!-- phil-dev source-sha256=fc37638fda32ef8a36e0966354ae036fc4a844d8d4a251465fbd7d78bc6f4026 -->
# Mener un jeu

## La réserve, la grille et les motifs

Une `SquarePool` contient de 1 à 500 cases aux identifiants distincts ; une `Square` a un libellé de 1 à 200 octets et,
dans un jeu coché par l'activité, un `Trigger`. Une `Grid` fait de 3 à 9 cellules de large, avec une case libre au
centre pour une taille impaire par défaut (`new Grid(4, freeCentre: false)` pour une taille paire) ; ses cellules sont
numérotées rangée par rangée à partir de 0. La réserve contient au moins autant de cases qu'un carton en tire : chaque
cellule sauf la case libre du centre.

`WinPatterns` énumère de 1 à 16 motifs, vérifiés dans l'ordre : le premier qui correspond nomme la victoire.

| Motif | Gagne avec |
|---|---|
| `WinPattern::row($grid)` | N'importe quelle rangée pleine |
| `WinPattern::column($grid)` | N'importe quelle colonne pleine |
| `WinPattern::diagonal($grid)` | L'une ou l'autre diagonale |
| `WinPattern::fullCard($grid)` | Chaque cellule |
| `WinPattern::fourCorners($grid)` | Les quatre coins |
| `WinPattern::custom('cross', $grid, [[2, 7, 10, 11, 12, 13, 14, 17, 22]])` | N'importe lequel de 1 à 64 ensembles de cellules bien à lui |
| `WinPatterns::line($grid)` | Une rangée, une colonne ou une diagonale |

La case libre du centre compte comme cochée dans chaque motif.

## Deux façons de cocher

| `TickMode` | Coché par | Gagné |
|---|---|---|
| `Activity`, la valeur par défaut | Les gestes du joueur, enregistrés dans le journal d'activité | De lui-même, quand un geste complète un motif |
| `Called` | Les cases que l'animateur appelle, pour chaque carton | Quand son joueur le réclame et que les cases appelées complètent un motif |

## La distribution

`Bingo::deal($game, $player)` donne son carton à un joueur : distribué au premier appel, le même ensuite. Chaque carton a
un numéro de série dans son jeu, à partir de 1, et une disposition tirée du secret du serveur ; un numéro de série dont
la disposition est déjà celle d'un autre carton du jeu est sauté, si bien que deux cartons d'un jeu ne sont jamais
pareils. Un jeu distribue jusqu'à 100 000 cartons ; voir [Les cartons et leur germe](cards-and-seeds.md). Dans un jeu dont les cases sont appelées, `Bingo::print()`
distribue aussi des lots de cartons qu'aucun joueur ne détient, à imprimer ; voir [Cartons imprimés](printing.md).

## Cocher par l'activité

Un `Trigger` nomme un verbe du journal d'activité — exact, `talk.*` ou `*` — et, s'il est donné, l'unique objet sur
lequel il doit porter. `Activity\BingoActivityObserver` remet chaque entrée du journal à `Bingo` : un geste coche, sur
les cartons du membre dans les jeux ouverts cochés par l'activité, les cases qu'il déclenche, et `SquareTicked` est
envoyé pour chacune ; un carton dont il complète un motif gagne. Un geste annulé décoche ce que lui seul avait coché,
sauf si le carton a déjà gagné : une cellule reste cochée tant que l'un de ses gestes tient. Un geste enregistré deux
fois ne coche qu'une fois, grâce à sa clé d'idempotence.

## L'appel

`Bingo::call($game, $square)` appelle une case de la réserve pour chaque carton d'un jeu dont les cases sont appelées, et
envoie `SquareCalled` la première fois. Les cases cochées d'un carton sont les cases appelées qu'il contient, calculées
quand on le lit : un appel coûte donc la même chose quel que soit le nombre de cartons.

## Réclamations et vérifications

`Bingo::claim($game, $serial, $player)` est la réclamation du joueur sur son propre carton : refusée sur le carton d'un
autre joueur. Le serveur tire de nouveau la disposition du carton à partir de son germe et refuse un carton qui ne lui
correspond pas, puis lit ses cases cochées — les cases appelées, ou les gestes enregistrés — et fait gagner le carton
quand elles complètent un motif. `Bingo::verify()` est la même vérification par numéro de série seul, pour
l'organisateur ; `Bingo::verifyLabel()` vérifie d'abord un carton imprimé par son étiquette. Un carton d'un jeu fermé est vérifié, jamais gagnant.

La réponse est un `Claim` : les cellules cochées, case libre du centre comprise, et le motif avec lequel le carton a
gagné, ou null. Un carton gagne une seule fois : `BingoWon` est envoyé une fois, le jeu garde son numéro de série dans
`winners()` dans l'ordre, et le carton garde le motif et le moment.

## Fermer

`Bingo::close($game)` ferme un jeu : il ne distribue plus, n'appelle plus, ne coche plus et ne fait plus gagner de
cartons.
