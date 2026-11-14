<!-- phil-dev source-sha256=35e411319a5677d5d60bd57facaff44b2ac15605458625d2214d9d9e236bfade -->
# Référence

| Type | Rôle |
|---|---|
| `Ratings` | Enregistre chaque résultat une fois et remet de nouveau ses événements après un écouteur en échec, donne les cotes, dit lesquelles sont provisoires, donne le classement avec ou sans elles, publie et masque, rejoue, exporte et efface |
| `GameResult` | Une partie jouée : ses camps et leurs rangs ; écrite et relue en valeurs simples |
| `MemberRating` | La cote d'un membre à un jeu, telle que le dépôt de stockage la garde, à une version |
| `RatingChange` | Comment un résultat enregistré a fait bouger le score d'un membre |
| `Visibility` | `Private` ou `Public` |
| `Contract\Rating` | Une cote en nombres entiers, par nom |
| `Contract\RatingSystem` | Cote les membres d'un résultat, donne le score, dit si une cote est incertaine et relit une cote |
| `Contract\RatingStore` | Où les cotes sont gardées, avec les résultats que compte chaque jeu |
| `Contract\PlaySource` | D'où les résultats d'un jeu sont lus, pour une relecture |
| `Elo\EloSystem` | La cote Elo |
| `Elo\EloRating` | Une cote Elo en centièmes |
| `Elo\KFactor` | Un K fixe ou décroissant |
| `Elo\KFactorStep` | Un palier d'un K décroissant |
| `OpenSkill\OpenSkillSystem` | L'approximation bayésienne de Weng et Lin |
| `OpenSkill\OpenSkillModel` | `PlackettLuce` ou `ThurstoneMosteller` |
| `OpenSkill\SkillRating` | Une habileté, son mu et son sigma en millionièmes |
| `Event\RatingChanged` | Envoyé pour chaque membre d'un résultat enregistré |
| `Event\PeakReached` | Envoyé pour un membre au-dessus de son score le plus haut |
| `Store\InMemoryRatingStore` | Le dépôt de stockage en mémoire, pour les tests et les scripts |
| `Exception\RatingException` | L'interface de chaque exception de la bibliothèque |
| `Exception\InvalidRating`, `RatingRefused`, `ResultAlreadyRecorded`, `RatingConflict` | Voir [Exceptions](exceptions.md) |

`Identifier` et `OpenSkill\Gaussian` sont internes.
