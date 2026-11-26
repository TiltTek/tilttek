<!-- phil-dev source-sha256=958beec815073e28c26cbb8f8d6625f98cd52acc80c33599acc8217c715d52cd -->
# Fins, records et historique

## Le ton d'une fin

`Ending::of($outcome, $seat, $scoring, $best)` dit comment une partie s'est terminée pour son joueur, sur l'un de deux
tons.

| `Tone` | Quand | Ce que fait l'application |
|---|---|---|
| `Victory` | Le siège fait partie des gagnants de l'issue | Elle joue une célébration — la recette de célébration de son kit d'interface — annoncée dans une région vivante et coupée par une préférence pour le mouvement réduit |
| `Completed` | Toute autre fin | Un mot bienveillant et une invitation à rejouer un autre jour : jamais de message, de son ni d'animation moqueurs |

`Tone::celebrates()` est vrai pour une victoire seulement. Le jeu décide de ce qu'est une victoire : trouver le code,
dix bons choix de suite.

## Records

`Ending::$score` est le score que le jeu a rapporté pour le siège, ou nul. `Ending::$record` est vrai quand ce score est
un nouveau record par rapport à `$best`, le meilleur score du joueur avant cette partie, que l'application lit dans son
historique : le premier score est toujours un record ; un score égal ne l'est jamais, de sorte qu'un score égal
n'annonce jamais un record battu.

## Un historique sans série

`DailyHistory::of($scores, $order)` additionne les scores quotidiens d'un joueur pour un jeu : les jours joués, les
parties gagnées, le meilleur score, et `distribution`, le nombre de parties terminées sur chaque score, le meilleur
d'abord. Il refuse des scores de plusieurs jeux ou joueurs, ou deux scores d'un même jour.

Il ne compte aucune série, par conception : une journée manquée n'enlève rien, et rien dans la bibliothèque ne demande à
un joueur de revenir sous la menace de perdre ce qu'il a bâti. Une application qui montre les jours joués peut les
montrer sur une fenêtre — les jours joués ce mois-ci — plutôt que de suite.
