<!-- phil-dev source-sha256=05dc1e10268f2144e2ce1c41fed12d4b44b57c2069d830d1e327cce03102e3b1 -->
# Base de données

Le bundle ajoute cinq tables au schéma que l'ORM génère pour sa connexion ; sans l'ORM, créez-les avec
`Tilttek\EscapeRoomBundle\Infrastructure\Doctrine\EscapeRoomTables::configure($schema)`. Les moments sont écrits par le
type `datetime_utc_immutable`, en UTC, à la seconde.

## `tilttek_escape_room_game`

| Colonne | Contient |
|---|---|
| `id` | L'identifiant de la partie, 64 caractères au plus ; la clé primaire |
| `room`, `team`, `session` | La salle, la référence de l'équipe et la séance, à côté de l'état pour les requêtes |
| `deadline` | Le moment où la minuterie atteint zéro ; null tant que la partie est en pause ou terminée ; indexé |
| `state` | Le JSON des valeurs simples qu'écrit `Game::toArray()`, chaque moment en UTC à la microseconde |

## `tilttek_escape_room_result`

| Colonne | Contient |
|---|---|
| `game` | La partie ; la clé primaire |
| `room`, `team`, `session` | Comme ci-dessus |
| `final_seconds`, `played_seconds`, `penalty_seconds`, `hints`, `skips` | Le résultat |
| `escaped_at`, `escaped_micro` | Le moment de l'évasion, et ses microsecondes, qui classent deux équipes échappées dans la même seconde |
| `public` | Si l'équipe a accepté de le montrer |

L'index `tilttek_escape_room_result_rank` lit dans l'ordre le classement d'une salle.

## `tilttek_escape_room_screen`

Une ligne par partie : ce que montre l'écran d'indices — `kind` (`hint`, `message` ou `cleared`), l'animateur `host` qui
l'a montré, `shown_at`, et `puzzle`, `tier` et `penalty` d'un indice ou le `message`, de 280 caractères au plus.

## `tilttek_escape_room_booking`

Les réservations des séances : `id`, `session`, `room`, `party`, `seats`, `private`, `booked_at`, et `position`, l'ordre
de la réservation dans sa séance.

## `tilttek_escape_room_session_lock`

Une ligne par séance réservée, écrite par chaque réservation avant qu'elle décide : le verrou des
[réservations](bookings.md).
