<!-- phil-dev source-sha256=280a829a1d0003deb9f7caa8dfb7ff2632cea1e23d8f510e515d688c1ed9df1a -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/session-scheduler` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Series` : le calendrier d'une campagne — sa première séance dans son fuseau horaire, une règle RFC 5545 lue par
  `phil/recurrence`, son meneur de jeu, jusqu'à 50 joueurs, les minutes d'une séance, le quorum, le préavis d'une
  absence (24 heures par défaut) et un rappel ou aucun — borné et vérifié.
- `Session`, `SessionStatus` et `Reply` : une séance, l'occurrence qu'elle représente, si elle est hors calendrier, son
  statut, son meneur de jeu remplaçant et les réponses des membres.
- `SessionScheduler` : créer une série, et la modifier par son meneur de jeu, qui peut la confier à un autre ; il ne
  garde aucun état entre les appels et lit l'heure dans une horloge PSR-20.
- `SessionScheduler::plan()` : les séances des occurrences de la règle planifiées par lots de jusqu'à 100, de façon
  idempotente, une occurrence déjà planifiée, déplacée ou annulée n'étant jamais planifiée de nouveau.
- `SessionScheduler::add()` : une séance à n'importe quel début après maintenant, marquée hors calendrier quand elle
  n'est pas une occurrence de la règle, jamais refusée pour cette raison.
- `SessionScheduler::reschedule()` et `postpone()` : une séance déplacée, ou une séance et chacune des suivantes
  déplacées du même nombre de jours sur leur heure locale pour que leur espacement reste, les réponses abandonnées.
- `SessionScheduler::cancel()`, `hold()` et `substitute()` : une séance annulée, tenue une fois commencée, ou menée par
  un meneur de jeu remplaçant qui n'est pas un joueur.
- `SessionScheduler::confirm()` et `absent()` : un membre qui vient ou qui est absent, une absence annoncée après le
  préavis enregistrée et marquée comme tardive, le meneur de jeu qui mène la séance absent, et un plafond facultatif de
  séances à venir par membre.
- Le quorum : le meneur de jeu averti quand des absences font passer les joueurs sous le quorum et quand il revient, la
  séance jamais annulée d'elle-même.
- `DateProposal`, `Contract\DateProposer`, `SessionScheduler::propose()` et `proposeReplacement()` : des dates pour une
  première séance, ou les jours libres à l'heure de la série pour en remplacer une, proposées aux membres.
- `Reminder` et `SessionScheduler::reminders()` : les rappels dus dans une fenêtre de jusqu'à 31 jours, aux membres qui
  ne sont pas absents.
- `SessionQuery` et `SessionScheduler::sessions()` : les séances d'une série ou de toutes les séries, dans une fenêtre
  et un statut, la plus proche en premier.
- Les événements `SeriesCreated`, `SeriesChanged`, `DatesProposed`, `SessionPlanned`, `SessionRescheduled`,
  `SessionCancelled`, `SessionHeld`, `AttendanceConfirmed`, `AbsenceAnnounced`, `GameMasterAbsent`,
  `SubstituteAssigned`, `QuorumLost` et `QuorumRestored`, par identifiants seulement, annoncés une seule fois à un
  `Contract\SessionObserver`.
- `Contract\SessionStore`, gardé à une version, lu par requête et qui compte les séances à venir d'un membre, et
  `Store\InMemorySessionStore` pour les tests.
- Les exceptions `InvalidSchedule`, `ScheduleRefused`, `ScheduleNotFound` et `ScheduleConflict`, sous
  `SchedulerException`.
- L'adaptateur d'un sondage de planification, documenté.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
