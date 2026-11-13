<!-- phil-dev source-sha256=bee1c51320171783664929cd67c70873c9f46eef7a624e0a1a3bcbf934d083cc -->
# Stockage

`Contract\BingoStore` garde les jeux et leurs cartons ; `Store\InMemoryBingoStore` les garde en mémoire, pour les tests
et les scripts.

| Méthode | Fait |
|---|---|
| `game()`, `addGame()`, `saveGame()` | Lit, ajoute à la version 1 et enregistre la version suivante d'un jeu |
| `card()`, `cardOf()` | Lit un carton par numéro de série, cartons imprimés compris, ou le carton qu'un joueur détient dans un jeu |
| `addCard($card, $fingerprint)` | Ajoute un carton à la version 1 ; retourne false, sans rien garder, quand un autre carton du jeu a l'empreinte |
| `saveCard()` | Enregistre la version suivante d'un carton |
| `openCardsOf($player, $limit)` | Les cartons du joueur dans les jeux ouverts, au plus `$limit` ; jamais un carton imprimé |

## Les versions

Un jeu et un carton comptent leurs changements : chacun leur donne leur version suivante, à partir de 1. `save…()`
refuse avec `BingoConflict` quand la version stockée n'est pas celle d'avant ; `addGame()` refuse un identifiant pris,
et `addCard()` un numéro de série pris ou un second carton pour un joueur ; un carton imprimé n'a pas de joueur, et un jeu en
détient autant qu'il en imprime. `Bingo` relit le jeu ou le carton et refait
le changement en cas de conflit, jusqu'à `Bingo::ATTEMPTS` fois : deux gestes enregistrés en même temps cochent tous
les deux, l'un après l'autre. Un dépôt adossé à une base de données écrit `save…()` comme une mise à jour conditionnée
par la version, et garde les empreintes d'un jeu sous un index unique.

## Ce que garde un dépôt

Un jeu garde sa réserve, sa grille, ses motifs et son mode, le nombre de cartons distribués, les cases appelées et les
numéros de série gagnants. Un carton garde son jeu, son numéro de série, la référence de son joueur — aucune pour un carton imprimé —, sa disposition, les
clés d'idempotence des gestes qui ont coché chaque cellule, ainsi que le motif et le moment de sa victoire. Aucun
libellé écrit par un joueur et aucun nom n'est gardé : les joueurs sont les références que donne l'application.
