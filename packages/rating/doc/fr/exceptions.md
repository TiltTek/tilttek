<!-- phil-dev source-sha256=f903c684169e45404f6b257f33e8e37e3318dc4cd433fa607bf466f40be744df -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Rating\Exception\RatingException`.

| Exception | Lancée quand | Message, par exemple |
|---|---|---|
| `InvalidRating` | Un identifiant, un résultat, un réglage d'un système, une limite du classement ou des valeurs stockées sont refusés, ou une source donne un résultat d'un autre jeu, hors d'ordre ou en double | `A result has 2 to 64 sides.` |
| `RatingRefused` | Les cotes d'un jeu ont été calculées par un autre système, on demande si une cote d'un autre système est provisoire, ou une relecture n'a pas de source de résultats | `The ratings of the game chess were computed by the system elo; replay the game to rate it with the system openskill-pl.` |
| `ResultAlreadyRecorded` | Un résultat que son jeu compte déjà, ses événements tous remis, est enregistré de nouveau : un double clic, ou un message livré deux fois | `The result play-81 of the game chess is recorded already.` |
| `RatingConflict` | Une cote a changé ou a été ajoutée depuis sa lecture : un autre résultat des mêmes membres a été enregistré en même temps | `The rating of the member member-1042 for the game chess changed since it was read.` |

`InvalidRating` étend `InvalidArgumentException`, `RatingRefused` et `ResultAlreadyRecorded` étendent
`DomainException`, et `RatingConflict` étend `RuntimeException`.
