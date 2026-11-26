<!-- phil-dev source-sha256=dd32e47441e10c16d5784a73cde6ca269858c629ea4957b9061a22dde0565772 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `AttendanceRecorder` | Enregistre une fois l'admission d'une lecture, lue dans le registre, avec sa première session, journée, lieu et semaine |
| `Contract\PresenceResolver` | Ce que l'application dit d'un enregistrement : la présence, ou null pour une admission qui ne récompense personne |
| `Presence` | Le membre, la session, l'événement, le lieu, la piste et le fuseau horaire d'une admission |
| `AttendanceVerbs` | Les verbes que la bibliothèque enregistre, et leur motif `attendance.*` |
| `AttendanceCriterion` | Une règle de présence — sessions, chaque jour, lieux distincts, un lieu, semaines — pour les badges et les quêtes |
| `AttendanceReader` | La présence d'un membre, lue par ce membre seulement |
| `AttendanceSummary` | Les sessions, jours et lieux distincts, d'un événement ou de tous, et les semaines de présence |
| `Exception\CheckInRewardsException` | Toutes les exceptions de la bibliothèque |
| `Exception\InvalidPresence` | Une présence dont l'événement, le lieu ou la piste ne tient pas dans le contexte d'une activité |
| `Exception\InvalidCriterion` | Un critère dont le compte est hors limites, ou dont l'événement ou le lieu ne convient pas |
| `Exception\AccessDenied` | Un lecteur qui demande la présence d'un autre membre |
