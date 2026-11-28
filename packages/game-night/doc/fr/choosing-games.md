<!-- phil-dev source-sha256=b97de2d180059629d78633afeef0a2035f7266b7cf06aa7bef3a25f24d5afe29 -->
# Choisir des jeux

`Choice\GameChooser` suggère des jeux pour une table, parmi les jeux que les membres peuvent apporter et ceux que le
club ou la boutique conserve.

## Les critères

`Choice\Criteria` contient ce pour quoi un jeu est choisi :

| Valeur | Ce que c'est | Bornes |
|---|---|---|
| `players` | Les joueurs, invités compris | 1 à 1500 |
| `minutes` | Les minutes disponibles | 1 à 1440 |
| `owners` | Les membres dont les jeux peuvent être apportés | 1500 au plus, chacun une seule fois |
| `library` | Les jeux du catalogue que le club ou la boutique conserve | 500 au plus, chacun une seule fois |
| `limit` | Le plus grand nombre de suggestions à donner | 1 à 50, 10 par défaut |

`Criteria::forTable()` prend les places occupées à une table, ou ses sièges tant que personne n'y est assis, les
minutes de la soirée, et les membres confirmés qui y sont assis. `Criteria::forNight()` prend les places confirmées de
toute la soirée, ses minutes et ses membres confirmés.

## Les étagères

Les jeux des membres sont lus par `Contract\ShelfReader`, 1000 au plus : la bibliothèque ne lit jamais autrement la
collection d'un membre. Un jeu que possède un membre non nommé dans les critères est écarté.

## Quels jeux sont suggérés

Un jeu est suggéré quand le catalogue le contient et ne le cache pas, que son éditeur indique des nombres de joueurs qui
comprennent les joueurs, et qu'il se termine dans les minutes disponibles : la plus longue durée de jeu que l'éditeur
indique est au plus égale aux minutes disponibles. Un jeu dont le catalogue ne connaît pas la durée est suggéré après
ceux dont la durée est connue.

## L'ordre

Les suggestions viennent dans cet ordre :

1. les jeux que les membres du catalogue ont votés les meilleurs pour ce nombre de joueurs, puis recommandés, puis sans
   vote, puis non recommandés ;
2. parmi eux, les jeux dont la durée de jeu est connue ;
3. puis les jeux que le plus de gens peuvent apporter : leurs propriétaires, et le club ou la boutique quand il les
   conserve ;
4. puis par nom, puis par identifiant.

## Les votes sur lesquels repose une suggestion

Un choix issu d'un vote se classe comme un choix issu de cinquante. Chaque `Choice\Suggestion` porte ce sur quoi elle
repose, à afficher à côté d'elle :

| Valeur | Ce que c'est |
|---|---|
| `choice` | Le choix que la plupart des membres du catalogue ont donné pour ce nombre de joueurs, ou `null` |
| `choiceVotes` | Combien de membres ont donné l'un des trois choix pour ce nombre |
| `averageRating` | La note moyenne de ce nombre de joueurs, en centièmes, ou `null` |
| `ratingVotes` | Combien de membres l'ont notée |

## BoardGameGeek

Une suggestion conserve l'identifiant du jeu sur BoardGameGeek et construit le lien vers sa page, par
`boardGameGeekLink()` ; rien d'autre de BoardGameGeek n'est lu ni conservé.
