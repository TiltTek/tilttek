<!-- phil-dev source-sha256=566d0f2e73f6bb51ca78ec069b6a487ed40a8cf93350bfd0e760333eda728728 -->
# Présence et rappels

## Vient et absent

Un membre répond pour une séance avec `confirm($session, $member)` ou `absent($session, $member)` : un joueur de la
série ou le maître de jeu qui dirige la séance, le remplaçant quand il y en a un. La séance est planifiée et n'a pas
commencé. Répondre de nouveau de la même façon ne change rien ; un membre change sa réponse en donnant l'autre.

| Réponse | Sens |
|---|---|
| aucune | Le membre n'a pas répondu : il compte comme venant pour le quorum. |
| `Reply::Coming` | Il a dit qu'il vient. |
| `Reply::Absent` | Il a dit qu'il est absent au moins le préavis de la série avant la séance. |
| `Reply::AbsentLate` | Il l'a dit après le préavis : enregistré tout de même, pour que le maître de jeu en décide. |

Une absence est en retard quand le moment où elle est annoncée est postérieur au début de la séance moins le préavis ;
annoncée exactement au préavis, elle n'est pas en retard. Le maître de jeu qui dirige la séance dit aussi qu'il est
absent : la séance attend alors un remplaçant, ou son annulation, et l'observateur reçoit `GameMasterAbsent`.

## Le quorum

Les joueurs non absents d'une séance sont comptés par rapport au quorum de la série. Quand une absence les fait passer
sous le quorum, l'observateur reçoit `QuorumLost` ; quand un joueur qui vient les ramène au quorum, `QuorumRestored`.
Chacun est annoncé une fois, au changement. La séance n'est jamais annulée d'elle-même : le maître de jeu décide.

## Le plafond de séances à venir

`SessionScheduler` accepte `maxComing`, de 1 à 100 : le plus grand nombre de séances planifiées commençant après
maintenant auxquelles un membre peut dire qu'il vient, dans toutes les séries. Un membre au plafond est refusé ;
confirmer une séance à laquelle il vient déjà n'est pas compté de nouveau. Sans plafond, il n'y a pas de limite.

## Proposer des dates

`propose($series, $candidates, $by)` propose au maître de jeu et aux joueurs de 1 à 20 débuts distincts après
maintenant, pour une première séance ou une séance à ajouter. `proposeReplacement($session, $through, $by, $count = 7)`
propose les jours de demain jusqu'à un moment, à l'heure de la série sur son horloge murale, où aucune autre séance
planifiée de la série ne commence (de 1 à 20, en parcourant au plus 366 jours) pour remplacer une séance planifiée ou
annulée ; une séance tenue n'est pas remplacée.

Les deux construisent un `DateProposal`, ses dates les plus anciennes d'abord, le remettent au `Contract\DateProposer`
et le retournent. La réponse revient plus tard : l'application appelle `add()` ou `reschedule()` avec la date choisie.

## Rappels

`reminders($after, $through)` liste les rappels dus après un moment jusqu'à un autre, une fenêtre d'au plus 31 jours, le
plus tôt dû d'abord. Un rappel est dû le nombre d'heures `reminder` de la série avant une séance planifiée ; une série sans
rappel n'en a aucun. Chaque `Reminder` nomme la série, la séance, son début, le moment où le rappel est dû et les membres
à rappeler : le maître de jeu qui dirige la séance, puis les joueurs, sans ceux qui ont dit qu'ils sont absents.

Le planificateur ne garde rien entre les appels : la tâche planifiée de l'application passe la fin de son appel
précédent comme début du suivant, pour que chaque rappel soit dû une fois.
