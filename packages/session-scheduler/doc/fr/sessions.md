<!-- phil-dev source-sha256=adbe9df57dfb2448bba6727b73ba4e5dfad0931d70fde7de29610bf9632fc0e0 -->
# Séries et séances

## Une série

Une `Series` est l'horaire d'une campagne :

| Valeur | Bornes | Sens |
|---|---|---|
| `id` | un identifiant d'au plus 96 caractères | la série ; les identifiants de ses séances y ajoutent leur instant |
| `campaign` | un identifiant | la campagne, qu'un changement ne déplace jamais |
| `gameMaster` | un identifiant | le membre qui modifie la série et ses séances |
| `start` | un moment, dans son fuseau horaire | la première séance |
| `rule` | une `RecurrenceRule` de `phil/recurrence` | comment les séances se répètent, `COUNT` et `UNTIL` compris |
| `players` | de 0 à 50 identifiants distincts, sans le maître de jeu | qui joue |
| `minutes` | de 15 à 1440, 180 par défaut | la durée d'une séance |
| `quorum` | de 1 au nombre de joueurs, 1 par défaut | le moins de joueurs non absents pour qu'une séance vaille la peine d'être tenue |
| `notice` | de 0 à 720 heures, 24 par défaut | combien de temps avant une séance une absence est annoncée ; après, elle est en retard |
| `reminder` | de 1 à 168 heures, ou null par défaut | quand le rappel d'une séance est dû, ou aucun |

Un identifiant compte de 1 à 128 lettres, chiffres, `_`, `.`, `:` ou `-`, et commence par une lettre : une référence
opaque, jamais un nom ni une adresse.

`SessionScheduler::createSeries()` garde une nouvelle série à la version 0. `changeSeries()` la remplace par un
changement fait sur la version lue, par son maître de jeu, qui peut confier la série à un autre membre ; la série reste
dans sa campagne. Les séances déjà planifiées gardent leurs dates et leurs réponses.

## Planifier

`plan($series, $through, $by, $limit = 100)` planifie les séances des occurrences de la règle, de maintenant jusqu'à un
moment :

- l'identifiant de chaque séance est l'identifiant de la série, `:`, et l'instant de l'occurrence en UTC
  (`iron-coast:20270306T000000Z`), pour que la planification soit idempotente ;
- une occurrence qui a déjà une séance est laissée telle quelle, même si cette séance a été replanifiée ou annulée
  depuis : planifier de nouveau ne planifie que ce qui est nouveau, et reprend là où l'appel précédent s'est arrêté ;
- au plus `$limit` séances, de 1 à 100, sont planifiées par un appel, à partir d'au plus 1000 occurrences lues.

Une séance planifiée à partir de la règle garde dans `occurrence` l'occurrence qu'elle représente, même replanifiée.

## Ajouter une séance

`add($series, $startsAt, $by)` ajoute une séance à un début postérieur à maintenant. Un début qui est une occurrence de
la règle donne la séance de cette occurrence, que la planification saute ensuite ; tout autre début donne une séance
marquée `offCalendar`, dont l'identifiant contient `:x` avant son instant. Une séance qui existe déjà pour ce début est
refusée.

## Replanifier et reporter

`reschedule($session, $startsAt, $by)` déplace une séance planifiée qui n'a pas commencé vers un autre début postérieur à
maintenant. Les réponses données pour l'ancien début sont abandonnées : les membres répondent de nouveau. La séance est
marquée hors calendrier quand le nouveau début n'est pas une occurrence de la règle ; la déplacer à son début actuel ne
change rien.

`postpone($session, $days, $by)` déplace une séance planifiée et chaque séance planifiée suivante de sa série de 1 à 366
jours, sur l'horloge murale du fuseau horaire de chaque séance : leur espacement reste. Au plus 100 séances bougent en
un appel. Un report de semaines entières d'une règle hebdomadaire garde les séances dans le calendrier.

## Annuler, tenir et remplacer

`cancel($session, $by)` annule une séance planifiée qui n'a pas commencé. Une séance annulée n'est jamais planifiée de
nouveau et ne change plus ; des dates pour la remplacer sont proposées comme le décrit la
[présence](attendance.md#proposer-des-dates).

`hold($session, $by)` marque tenue une séance planifiée une fois commencée, par le maître de jeu de la série ou par le
remplaçant qui l'a dirigée.

`substitute($session, $gameMaster, $by)` donne à une séance planifiée un maître de jeu remplaçant, qui répond de la
séance à la place de celui de la série ; un joueur de la série n'est jamais remplaçant. Null, ou le maître de jeu de la
série, le lui rend. Les réservations payées d'un maître de jeu ne relèvent pas de cette bibliothèque : une application
les réserve par une bibliothèque de réservation et assigne le remplaçant ici.

## Lire

`series()` et `session()` en donnent une par son identifiant. `sessions(SessionQuery $query, $limit = 1000)` donne les
séances d'une série ou de toutes les séries, commençant dans une fenêtre (les deux bornes comprises) et dans un statut,
les plus anciennes d'abord. `Session::isRescheduled()` dit si une séance a quitté son occurrence, et `endsAt()` quand
elle finit.

Seul le maître de jeu de la série planifie, ajoute, replanifie, reporte, annule, donne un remplaçant et propose des
dates. Une séance qui n'est pas planifiée, ou qui a commencé, ne change plus.
