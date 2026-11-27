<!-- phil-dev source-sha256=e320567a547abacbef26d8a34b43d6a9ff65a041b5ea43268c2b4d9af9adf2cc -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/check-in-rewards` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `AttendanceRecorder`, appelé avec l'identifiant d'un passage depuis un écouteur de l'événement `CheckInRecorded` de
  `phil/checkin-bundle` ou depuis un message : une admission — résultat admis, sans conflit —, lue telle que la garde le
  registre des enregistrements de `phil/checkin`, enregistrée une seule fois dans le journal de `tilttek/activity` sous
  l'identifiant du passage, avec la première admission du membre à la séance, le jour, au lieu et dans la semaine,
  chacune une seule fois sous une clé tirée de ce qui la rend unique ; les jours et les semaines ISO dans le fuseau
  horaire de l'événement ; un moment jamais postérieur à la réception par le serveur.
- `Contract\PresenceResolver` et `Presence` : ce que l'application dit d'un enregistrement — membre, séance, événement,
  lieu, volet, fuseau horaire —, null pour une admission qui ne récompense personne, comme un membre effacé.
- `AttendanceVerbs` : les verbes qu'enregistre la bibliothèque, et leur motif `attendance.*`.
- `AttendanceCriterion` : des séances — un passeport de convention —, chaque jour d'un événement, des lieux distincts,
  un lieu découvert et des semaines de présence sans pénalité pour une semaine manquée, écrits pour le `CriterionReader`
  de `tilttek/badges` et le `QuestReader` de `tilttek/quests`.
- `AttendanceReader` et `AttendanceSummary` : les séances, les jours, les lieux et les semaines distincts d'un membre,
  lus par ce seul membre ; aucun classement de la présence.
- `Exception\CheckInRewardsException`, `InvalidPresence`, `InvalidCriterion` et `AccessDenied`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
