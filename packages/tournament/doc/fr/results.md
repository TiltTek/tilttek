<!-- phil-dev source-sha256=8e5ac26d40debe9868e850dd7528d7f71d1b3f5584170a73c432380e2028ba43 -->
# Résultats, corrections et retraits

## Un résultat

Un `Result` contient la façon dont le match s'est terminé, du côté du participant qui a joué en premier, et, pour les
jeux qui les comptent, les points de mission de chaque côté en plus de la victoire, comme le font les jeux de
figurines — les deux côtés ou aucun, de 0 à 1 000 000 chacun.

| `Outcome` | Points, selon `Scoring` |
|---|---|
| `FirstWins`, `SecondWins` | Une victoire et une défaite. |
| `Draw` | Un nul chacun. |
| `FirstForfeits`, `SecondForfeits` | Une défaite par forfait et une victoire par forfait : une défaite et une victoire, sauf si le pointage les distingue. |
| `BothForfeit` | Une défaite par forfait chacun. |

Un forfait n'est pas une rencontre : les deux peuvent être appariés de nouveau dans une étape suisse, et une victoire par
forfait est exclue du bris d'égalité `wins`.

## Saisi une seule fois

`Tournaments::record()` prend un résultat pour une table, avec qui l'a saisi — un participant ou un organisateur, par
un identifiant opaque :

| Ce qui était gardé | Résultat saisi | `RecordingStatus` | Ce qui arrive |
|---|---|---|---|
| Rien | N'importe lequel | `Recorded` | Le résultat compte. |
| Le même résultat | Le même | `Unchanged` | Rien : un résultat saisi par les deux joueurs compte une seule fois. |
| Un autre résultat | Contraire | `Conflict` | La table n'a pas de résultat tant qu'un organisateur n'a pas tranché. |
| Un conflit | L'un de ceux saisis | `Conflict` | Rien de plus. |
| Un conflit | Un autre | `Conflict` | Il s'ajoute aux soumissions du conflit. |

`resolve()` tranche un conflit avec le résultat qu'un organisateur décide. Tant qu'un conflit attend, la ronde suivante
n'est pas préparée. Une exemption n'a pas de résultat à saisir.

## Corrections

`correct()` change le résultat gardé pour une table de la ronde courante ou d'une ronde passée. Le classement de son
étape est recalculé, et le `Recording` renvoyé énumère chaque `StandingChange` : le participant, le rang et les points
avant et après. Les rondes appariées depuis restent telles qu'elles ont été publiées. Une correction qui change le
podium renvoie de nouveau l'événement `TournamentFinished`, avec le nouveau podium.

Dans un tableau, le gagnant d'un match a peut-être déjà joué le suivant : la correction est refusée tant que le match
suivant n'a pas été corrigé d'abord, pour que le tableau ne contienne jamais un participant qui ne devrait pas s'y
trouver.

## Retraits

`withdraw()` retire un participant du tournoi. Il n'est plus apparié, et chacune de ses tables publiées sans résultat
devient un forfait : le sien, ou celui des deux quand l'adversaire s'est retiré aussi. Il reste au classement, à sa
place, marqué `withdrawn`, et ne se qualifie jamais pour une étape suivante. Dans un tableau, un participant qui se
retire avant de jouer perd son match par forfait, ce qui peut décider du tournoi.

## La fin du tournoi

Le tournoi est terminé quand sa dernière étape l'est : chaque ronde d'une étape suisse ou des poules jouée, ou la finale
du tableau décidée. Son podium est alors gardé — les trois premiers du classement, ceux qui se sont retirés étant
exclus, ou, après un tableau, le champion, le finaliste et les deux perdants des demi-finales, qui partagent la troisième
place —, et le `Recording` du résultat, de la décision, de la correction ou du retrait qui l'a terminé porte un
`Event\TournamentFinished` : le tournoi, le gagnant et le podium, des identifiants seulement.

La bibliothèque renvoie l'événement, et l'application le répartit, pour montrer une célébration sur la page du tournoi
ou pour envoyer ses avis. `key()` est la même chaque fois que le même podium est annoncé, pour qu'un écouteur fasse
chaque chose une seule fois.
