<!-- phil-dev source-sha256=bbfd8163483c224285203f45368829a8cb638d2e018045f6115f747a8e3d1773 -->
# Stockage

`Contract\RatingStore` garde un `MemberRating` par membre et par jeu :

| Méthode | Fait |
|---|---|
| `find($game, $members)` | Les cotes de ces membres pour le jeu, par membre ; un membre sans cote est omis |
| `hasRecorded($game, $result)` | Si le jeu compte déjà le résultat |
| `save($ratings, $result, $events)` | Garde les cotes d'un coup, chacune à sa version suivante, et compte le résultat pour leur jeu avec ses événements à remettre ; `ResultAlreadyRecorded` quand il est déjà compté, `RatingConflict` quand une cote a changé depuis sa lecture |
| `undelivered($game, $result)` | Les événements du résultat pas encore remis, dans l'ordre |
| `markDelivered($game, $result, ...$keys)` | Oublie les événements de ces clés, envoyés sans exception |
| `standings($game, $limit, $minGames)` | Les cotes publiques qui ont au moins `$minGames` parties cotées, et une au moins, le score le plus haut d'abord, puis par membre |
| `ofGame($game)` | Chaque cote du jeu |
| `replace($game, $ratings, $results)` | Remplace d'un coup chaque cote du jeu, et les résultats qu'il compte : ce qu'une relecture a calculé ; les événements du jeu pas encore remis sont annulés |
| `ofMember($member)` | Chaque cote du membre |
| `forget($member)` | Efface chaque cote du membre, et les événements à son sujet pas encore remis |

Une cote lue à la version 0 est nouvelle : le dépôt de stockage ne la garde que s'il n'en détient aucune pour ce membre
et ce jeu. Toute autre n'est gardée que si le dépôt la détient à la version où elle a été lue. Deux résultats des mêmes
membres enregistrés en même temps ne peuvent pas aboutir tous les deux : le second lance `RatingConflict`, rien n'en est
gardé, et l'application l'enregistre de nouveau. Dans une base de données, la ligne d'une cote porte sa version, mise à
jour avec `WHERE version = ?` dans une seule transaction pour tous les membres du résultat, avec une clé unique sur le
jeu et le membre.

Les résultats qu'un jeu compte sont gardés à côté des cotes : dans une base de données, une table du jeu et de
l'identifiant du résultat avec une clé unique sur les deux, écrite dans la même transaction que les cotes, pour qu'un
second enregistrement du résultat échoue sur la clé et ne garde rien ; `replace()` vide les lignes du jeu et ses
événements pas encore remis, et écrit celles de la relecture. Les identifiants sont des références opaques aux résultats, jamais un membre.

Les événements d'un résultat pas encore remis sont gardés dans la même transaction : dans une base de données, une
table du jeu, du résultat, de la clé de l'événement, du membre et de son genre (`changed` ou `peak`), avec une clé
unique sur le jeu, le résultat et la clé ; `markDelivered()` supprime ses lignes, et `forget()` celles du membre.

`MemberRating::toArray()` donne les valeurs simples à garder — la cote elle-même en nombres entiers, par nom — et
`MemberRating::fromArray($data, $system)` les relit avec le système qui les a calculées, en refusant une cote d'un autre
système. Le classement lit la colonne `score`, en centièmes, et la colonne `visibility` ; indexez-les par jeu.

`Store\InMemoryRatingStore` garde tout dans le processus, pour les tests et les scripts.
