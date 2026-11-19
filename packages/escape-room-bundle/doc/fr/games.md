<!-- phil-dev source-sha256=cbdde0f66ab14fbbc3c2db111909bcf7b33e5e696bad727beaefca93984a4e75 -->
# Parties

`Tilttek\EscapeRoomBundle\Application\GameDesk` joue les parties par leur identifiant. Chaque coup lit la partie dans le
magasin, la joue par `EscapeRooms`, la réécrit avec le moment où sa minuterie atteint zéro — aucun tant qu'elle est en
pause ou terminée —, enregistre le résultat d'une équipe qui s'est échappée, garde ce que montre l'écran d'indices, et
informe les écrans que la partie a avancé. Il ne garde rien entre deux appels.

## Lancer une partie

```php
$game = $desk->start('g-01J8', 'crypt', ResourceReference::of('team', 'owls'), session: 's-2026-11-14-19h');
```

L'identifiant est celui de l'application : de 1 à 64 caractères parmi `A-Z`, `a-z`, `0-9`, `-` et `_`, commençant par
une lettre ou un chiffre. Une partie déjà là, une salle que le catalogue ne détient pas et un identifiant mal formé
lancent `Application\Exception\DeskRefused` ; une équipe nommée par une adresse courriel ou du genre `erased` lance
`InvalidRoom`.

## Les coups

| Méthode | Ce qu'elle fait | Les écrans sont informés |
|---|---|---|
| `game($id)` | Lit la partie, comme un `StoredGame` : son identifiant, sa salle et sa `Game` | — |
| `timer($game)` | La minuterie, le temps joué et les pénalités | — |
| `puzzles($game)` | Les énigmes telles que les voit l'animateur : statut, indices révélés et restants | — |
| `screen($id)` | Ce que montre l'écran d'indices, ou null | — |
| `guess($id, $puzzle, $answer)` | Vérifie une proposition | `finish`, à l'évasion |
| `requestHint($id, $puzzle)` | Un indice demandé par les joueurs | — |
| `pushHint($id, $puzzle, $host)` | Un indice envoyé par l'animateur, montré sur l'écran d'indices | `hint` |
| `skip($id, $puzzle, $host)` | Une énigme sautée par l'animateur | `skip`, ou `finish` |
| `pause($id, $host)`, `resume($id, $host)` | Arrête et fait courir la minuterie | `pause`, `resume` |
| `message($id, $host, $text)` | Un message de l'animateur, montré sur l'écran d'indices | `message` |
| `clearScreen($id, $host)` | Efface l'écran d'indices | `clear` |
| `abandon($id)` | Met fin à la partie | `finish` |
| `expire($id)` | Met fin à la partie quand son temps est écoulé ; vrai quand elle a pris fin maintenant | `finish` |
| `expireDue($limit)` | Met fin aux parties dont la minuterie a atteint zéro, les plus anciennes d'abord | `finish`, pour chacune |
| `consent($id, $public)` | L'équipe accepte, ou n'accepte plus, de montrer son résultat | `consent` |
| `leaderboard($room, $limit)` | Le classement public d'une salle | — |

Les refus de la bibliothèque — une partie terminée, en pause ou hors temps, une énigme verrouillée, un message trop
long — passent tels quels, et rien n'est écrit quand un coup est refusé.

## Les résultats et le classement

Quand un coup met fin à la partie par une évasion, son `RoomResult` est enregistré sous l'identifiant de la partie. Le
classement lit les résultats que les équipes ont accepté de montrer, par temps final, puis par moins d'indices, puis par
première évasion, et les classe avec `Leaderboard::rank()` : les équipes au même temps final et au même nombre
d'indices partagent une place. Une équipe qui retire son consentement quitte le classement aussitôt.

## L'expiration

La minuterie ne reste jamais en marche : `expire()` met fin à une partie au moment où sa minuterie a atteint zéro, ses
pauses comptées, et `expireDue()` trouve les parties échues d'après le moment écrit avec chacune. Une partie déjà
terminée, ou dont le temps n'est plus écoulé, est laissée telle quelle ; appeler l'une ou l'autre deux fois met donc
fin à chaque partie une seule fois. La [planification](scheduling.md) l'exécute.
