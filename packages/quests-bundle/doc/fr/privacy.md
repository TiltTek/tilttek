<!-- phil-dev source-sha256=cb077322be7d0a9ddbf9a0b5623e477aea8e305b14b39823076e7c60506a6eeb -->
# Vie privée

Avec `phil/privacy-bundle` installé, le bundle enregistre le module `tilttek_quests`. La personne concernée est un
membre, désigné par `<member_kind>:<value>`, soit `member:44` par défaut.

**L'exportation** liste ce que l'activité du membre a apporté à chaque quête, ses acceptations et ses abandons, les
quêtes qu'il a accomplies lui-même et ses duels. Elle est vide quand les quêtes ne gardent rien de lui.

**L'effacement** supprime tout cela de quatre tables :

| Table | Effet |
|---|---|
| `tilttek_quests_contribution` | Supprimé |
| `tilttek_quests_enrolment` | Supprimé |
| `tilttek_quests_completion` | Supprimé : les accomplissements propres au membre |
| `tilttek_quests_duel` | Supprimé : les duels du membre, de quelque camp qu'il soit |

Ce qu'une équipe ou la communauté a accompli reste : cela n'appartient pas au membre, et les récompenses ont été
données. Le bundle n'affiche rien de public au sujet d'un membre. Afficher les quêtes d'un membre sur un profil est le
choix de l'application et celui du membre.
