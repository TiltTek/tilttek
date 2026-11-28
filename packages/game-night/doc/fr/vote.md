<!-- phil-dev source-sha256=0f245c85542690172e9a78f787f26cc0c0e41fe1e7d6b5de770941e381616827 -->
# Le vote

Avant la soirée, ses membres votent sur les jeux à jouer.

## Proposer des jeux

`GameNights::propose()` fixe les jeux proposés au vote, 30 au plus, chacun une seule fois, à la place de ceux proposés
auparavant. Chaque jeu est dans le catalogue, et n'y est pas caché. Les votes pour un jeu qui n'est plus proposé sont
abandonnés.

## Voter

`vote()` enregistre le vote d'un membre inscrit — confirmé ou en attente — pour un jeu proposé : un vote par membre, un
nouveau vote remplaçant le précédent. `withdrawVote()` le retire, et un membre qui retire son inscription retire son
vote avec elle. Les votes se ferment quand la soirée commence.

## Le résultat

`result()` donne un `Vote\VoteResult` :

| Valeur | Ce que c'est |
|---|---|
| `votes` | Combien de membres ont voté |
| `tallies` | Chaque jeu proposé, avec ses votes et sa part des votes en pourcentage, arrondie à la hausse à la moitié ; le plus de votes d'abord, puis dans l'ordre où ils ont été proposés |
| `leaders` | Les jeux qui ont le plus de votes, dans l'ordre où ils ont été proposés ; aucun sans vote |
| `outcome` | `NoVotes`, `Winner` ou `Tie` |

`winner()` donne le jeu qui a gagné, ou `null` sans vote ou en cas d'égalité. Une égalité est présentée comme une
égalité : l'hôte choisit entre les meneurs, la bibliothèque ne tire pas au sort.
