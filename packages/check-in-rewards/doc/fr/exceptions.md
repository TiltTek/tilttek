<!-- phil-dev source-sha256=1cc30341dfda812b0917f707ca03795136811128eaf3576bb12ac7d3bd96cf32 -->
# Exceptions

Chaque exception du paquet implémente `Exception\CheckInRewardsException` : interceptez celle-ci pour les intercepter
toutes.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidPresence` | Une `Presence` dont la référence d'événement, le lieu ou la piste ne convient pas dans le contexte d'une activité : 1 à 100 octets | `The place of a presence is 1 to 100 bytes; this one is 0.` |
| `InvalidCriterion` | Un `AttendanceCriterion` qui compte moins de 1 ou plus de 1 000 000 000, ou qui nomme un lieu ou une référence d'événement qui ne tient pas dans 1 à 100 octets | `An attendance criterion counts 1 to 1000000000; this one counts 0.` |
| `AccessDenied` | `AttendanceReader` interrogé par un spectateur sur la présence d'un autre membre | `"member-12" cannot read the attendance of "member-7": a member's attendance is theirs alone.` |

`InvalidPresence` et `InvalidCriterion` étendent `InvalidArgumentException` ; `AccessDenied` étend
`RuntimeException`. La borne de 100 octets est `Presence::MAX_CODE_LENGTH`, la longueur d'une chaîne dans le contexte
d'une activité de `tilttek/activity` ; la borne d'un compte est `AttendanceCriterion::MAX`.

Un scan qui n'est pas une admission — inconnu du registre, avec un autre résultat qu'admis, ou en conflit entre
l'appareil et le serveur — n'est pas une erreur : `AttendanceRecorder::record()` renvoie `false`, comme lorsque le
`PresenceResolver` renvoie `null`.

Les exceptions de `tilttek/activity` parviennent à l'appelant telles quelles : `UnknownVerb` quand le journal déclare
ses verbes sans `attendance.*`, `InvalidActivity` quand le scan est daté hors de la fenêtre du journal, et
`IdempotencyConflict` quand un scan est résolu vers une autre présence que la première fois.
