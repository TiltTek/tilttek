<!-- phil-dev source-sha256=d4d75c7c137abc339a60e89e3daec0cdf4efd04e7a7bd9ecb9c2ed77092b74fa -->
# Tables et tours

## Les tables

| Appel | Ce qu'il fait |
|---|---|
| `open($game, $tableId, $seats, $options, $seed)` | Ouvre une table d'un jeu de taverne ; son premier siège joue le premier |
| `play($tableId, $move, $expected)` | Joue un coup ; refusé par `ConcurrentMove` quand `$expected` n'est pas le nombre de coups |
| `timeout($tableId, $seat, $sequence)` | Joue le délai du tour d'un siège, quand il s'applique encore |
| `view($tableId, $seat)` | Ce qu'un siège voit de la table — l'auditoire, sans siège |
| `state($tableId)` | L'état entier, secrets compris : pour le serveur seulement |
| `ending($tableId, $seat)` | Comment la partie s'est terminée pour un siège, ou nul tant qu'elle se poursuit |

## Les tours propres au jeu

Un jeu de taverne termine un tour selon ses règles — un dé qui montre 1, une carte jouée, le troisième lancer —, si bien
que sa phase laisse les tours au jeu : l'ordre des tours du moteur est `Free`, et le jeu garde `Turns` dans ses
données — les sièges dans l'ordre où ils jouent et le siège qui joue — et refuse, dans `allows()`, le coup de tout autre
siège. `TavernGame::toAct()` dit à qui est le tour, nul une fois la partie terminée ; les vues le donnent comme `turn`.

## Le minuteur de tour

La bibliothèque ne dépend d'aucune horloge. Une application qui donne un délai aux tours implémente
`Contract\TurnTimer` :

| Méthode | Quand |
|---|---|
| `started($tableId, $seat, $sequence)` | Le tour d'un siège commence, après `$sequence` coups de la table : à l'ouverture, et après chaque coup qui laisse la partie se poursuivre |
| `stopped($tableId)` | La partie est terminée |

Le minuteur choisit le délai — un réglage de la table, de l'application — et, quand il s'écoule, appelle
`TavernTables::timeout($tableId, $seat, $sequence)` avec ce qu'on lui a dit. Le délai n'est joué que si le siège joue
encore après le même nombre de coups : un siège qui a joué à temps, ou une partie terminée, en fait une opération sans
effet qui rend l'état. C'est le coup `tavern.timeout` (`{seat}`), fait par la table elle-même, et le jeu termine le
tour selon ses règles : Pig garde ses points, Navire, Capitaine et Équipage s'arrête, Crazy Eights pige une carte
quand aucune n'a été piochée, puis passe. Un délai n'enlève jamais plus que le tour.

`Store\NullTurnTimer`, la valeur par défaut, ne garde aucune horloge : un tour dure jusqu'à ce qu'il soit joué.

## Fins

`Ending::of($outcome, $seat)`, que donne `TavernTables::ending()`, raconte la fin d'une partie sur l'un de trois tons.

| `Tone` | Quand | Ce que fait l'application |
|---|---|---|
| `Victory` | Le siège est le seul gagnant | Elle joue une célébration — la recette de célébration de son kit d'interface — annoncée dans une région vivante et coupée par une préférence pour le mouvement réduit |
| `Tie` | Le siège partage la victoire avec d'autres | La même célébration : une victoire partagée est une victoire |
| `GoodGame` | Le siège n'a pas gagné, ou personne n'a gagné, ou la vue est celle de l'auditoire | Un mot bienveillant et une invitation à rejouer : jamais de message, de son ni d'animation moqueurs |

`Tone::celebrates()` est vrai pour une victoire, seule ou partagée. `Ending::$reason` est le mot du jeu pour la fin et
`Ending::$winners` les sièges qui ont gagné.
