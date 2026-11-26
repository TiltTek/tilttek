<!-- phil-dev source-sha256=286472bf74cc5ea55f67e127b355cb56c7ceb996cb83792209dcdf108ea48ae5 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Séries et séances

| Type | Rôle |
|---|---|
| `SessionScheduler` | Le service : séries, planification, séances, présence, remplaçants, propositions et rappels. |
| `Series` | L'horaire d'une campagne : sa première séance, sa règle, son maître de jeu, ses joueurs et les réglages de la table. |
| `Session` | Une séance : son début, ses minutes, son occurrence, son statut, son remplaçant et ses réponses. |
| `SessionStatus` | Planifiée, annulée ou tenue. |
| `Reply` | Vient, absent, ou absent après le préavis. |
| `SessionQuery` | Quelles séances lire : d'une série ou de toutes les séries, dans une fenêtre et un statut. |
| `DateProposal` | Des dates proposées aux membres : pour une nouvelle séance ou pour en remplacer une. |
| `Reminder` | Un rappel dû pour une séance, et les membres à rappeler. |

## Ports et stockages

| Type | Rôle |
|---|---|
| `Contract\SessionStore` | Garde les séries et les séances à une version, lit les séances par requête et compte les séances à venir d'un membre. |
| `Contract\SessionObserver` | Reçoit les événements d'une série. |
| `Contract\DateProposer` | Propose aux membres les dates d'une proposition. |
| `Store\InMemorySessionStore` | Les séries et les séances en mémoire, pour les tests. |

## Événements

| Type | Rôle |
|---|---|
| `Event\ScheduleEvent` | Chaque événement : la série. |
| `Event\SessionEvent` | Les événements d'une séance : la série et la séance. |
| `Event\SeriesCreated` | Une série a été créée. |
| `Event\SeriesChanged` | Une série a été modifiée. |
| `Event\DatesProposed` | Des dates ont été proposées aux membres. |
| `Event\SessionPlanned` | Une séance a été planifiée ou ajoutée. |
| `Event\SessionRescheduled` | Une séance a bougé. |
| `Event\SessionCancelled` | Une séance a été annulée. |
| `Event\SessionHeld` | Une séance a eu lieu. |
| `Event\AttendanceConfirmed` | Un membre vient. |
| `Event\AbsenceAnnounced` | Un joueur est absent. |
| `Event\GameMasterAbsent` | Le maître de jeu qui dirige une séance est absent. |
| `Event\SubstituteAssigned` | Un remplaçant dirige une séance, ou le maître de jeu de la série de nouveau. |
| `Event\QuorumLost` | Une séance est tombée sous le quorum. |
| `Event\QuorumRestored` | Une séance est revenue au quorum. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\SchedulerException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidSchedule` | Une valeur hors de sa forme ou de ses bornes. |
| `Exception\ScheduleRefused` | Un changement que le planificateur n'autorise pas. |
| `Exception\ScheduleNotFound` | Aucune série ni séance n'a l'identifiant. |
| `Exception\ScheduleConflict` | La série ou la séance a changé depuis sa lecture. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme d'un identifiant, et les listes de membres distincts. |
| `Internal\Moment` | Des instants comparés et écrits par leurs secondes depuis l'époque. |
