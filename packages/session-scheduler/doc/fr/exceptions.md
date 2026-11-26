<!-- phil-dev source-sha256=ba8c608a8691268f778e461f10b2b7dcb75d98fedd640b4057ec6f8c644ea8d1 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\SchedulerException` : interceptez-la pour les intercepter toutes.

| Exception | Levée quand |
|---|---|
| `Exception\InvalidSchedule` | Une valeur sort de sa forme ou de ses bornes : un identifiant, les joueurs, les minutes, le quorum, le préavis, le rappel, une version, les réponses, les dates d'une proposition, une limite, un nombre, les jours d'un report, le plafond, une fenêtre qui finit avant de commencer ou une fenêtre de rappels de plus de 31 jours. |
| `Exception\ScheduleRefused` | Le planificateur n'autorise pas le changement : une nouvelle série n'est pas à la version 0 ou a l'identifiant d'une série gardée, le membre n'est pas le maître de jeu de la série, un changement déplace une série vers une autre campagne, un début est passé, une séance existe déjà pour ce début, la séance n'est pas planifiée ou a commencé, une séance est tenue avant son début ou par quelqu'un qui ne la dirige pas et ne dirige pas sa série, un remplaçant est un joueur, le membre ne joue pas et ne dirige pas la séance, le membre a atteint le plafond, une séance tenue est remplacée, ou aucun jour n'est libre pour un remplacement. |
| `Exception\ScheduleNotFound` | Aucune série ni aucune séance n'a l'identifiant. |
| `Exception\ScheduleConflict` | La série ou la séance a changé depuis sa lecture : relisez-la et refaites le changement. |

Une règle que `phil/recurrence` ne peut pas lire lève son `InvalidRule` avant la construction d'une série. Rien n'est
gardé quand une exception est levée, sauf par `plan()` et `postpone()`, qui gardent chaque séance au fur et à mesure :
un conflit sur l'une laisse gardées celles qui la précèdent, et un nouvel appel continue.
