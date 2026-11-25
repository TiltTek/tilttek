<!-- phil-dev source-sha256=7be5ed72e41088217176ea7a5397c3839d17866516745a38e253774f851600ad -->
# Vie privée

Avec `phil/privacy-bundle` installé, `EscapeRoomPrivacy` prend part à une demande d'accès ou d'effacement, sous le
module `tilttek_escape_room`. La personne concernée est un membre, référencé sous le genre `privacy.member_kind` —
`member` par défaut.

## Export

| Partie | Ce qu'elle contient |
|---|---|
| `games` | Les parties que le membre a jouées comme équipe, sous forme des valeurs simples qu'écrit la bibliothèque |
| `results` | Leurs résultats : la salle, la séance, les temps, les indices, les sauts, l'évasion et le consentement |
| `screens` | Les écrans d'indices qu'il a montrés en dernier comme animateur : le genre, le moment, l'énigme et le palier, le message |
| `bookings` | Les réservations qu'il a faites comme groupe : l'identifiant, la séance, les places, et si elle est privée |

Une partie sans ligne est omise ; un membre qui n'en a aucune obtient un export vide.

## Effacement

L'effacement décide colonne par colonne. Là où une ligne reste, elle porte un pseudonyme que l'effacement tire pour
lui-même — `erased:` et 32 chiffres hexadécimaux aléatoires —, que rien dans le bundle ne rattache au membre.

| Colonne | Effet | Pourquoi |
|---|---|---|
| `tilttek_escape_room_game.team` | Supprimée, ou pseudonymisée tant qu'une partie est en cours | Une partie terminée ne sert plus à personne une fois son résultat enregistré : elle est supprimée. Une partie encore en cours est la séance vivante de la salle : sa minuterie, sa console et son écran continuent de fonctionner sous le pseudonyme, le consentement à montrer le résultat étant retiré, et la partie se termine comme d'habitude. |
| `tilttek_escape_room_result.team` | Supprimée, ou pseudonymisée tant qu'une partie est en cours | Un résultat n'est lu que par le classement, et seulement quand l'équipe a consenti à le montrer. Retiré, il ne sert à rien ; gardé, sa salle, sa séance et son moment à la microseconde près renverraient à la réservation. Le supprimer laisse les places des autres équipes telles qu'elles sont. Une partie encore en cours enregistre son résultat sous le pseudonyme, caché du classement. |
| `tilttek_escape_room_screen.game` | Supprimée, ou pseudonymisée tant qu'une partie est en cours | Un écran d'indices sert tant que sa partie est en cours : les écrans des parties terminées du membre sont supprimés avec elles, et le message de l'animateur avec eux. |
| `tilttek_escape_room_screen.host` | Supprimée, ou pseudonymisée tant qu'une partie est en cours | Un écran que le membre a montré comme animateur est supprimé une fois sa partie terminée ou disparue. Tant que la partie est en cours, l'équipe lit encore l'indice : l'écran reste, avec le pseudonyme comme animateur. |
| `tilttek_escape_room_booking.party` | Pseudonymisée | Une réservation détient des places d'une séance que d'autres groupes partagent : la supprimer redonnerait ses places et changerait l'ordre de la séance. Elle reste avec le pseudonyme. Son identifiant est celui de l'application et peut se rattacher aux commandes ou aux paiements du site : la ligne reste donc une donnée personnelle pour qui les détient, et l'application supprime les réservations des séances passées selon sa propre conservation. |

Le rapport nomme chaque colonne avec son effet : `deleted` quand rien du membre ne reste en cours,
`pseudonymised` quand une partie du membre est encore en cours ou pour les réservations. Le verrou d'une séance,
`tilttek_escape_room_session_lock`, ne nomme personne et n'est pas dans le rapport.

Une référence du genre `erased` donnée par l'appelant — comme équipe, groupe ou animateur — est refusée par la
bibliothèque avec `InvalidRoom` : seul un effacement en écrit une.

## Ce qui n'est jamais gardé

Les mises à jour de Mercure ne nomment que la partie et le coup. Le message de Messenger de l'horaire, `ExpireDue`, ne
transporte rien. Une équipe, un groupe et un animateur sont nommés par des identifiants stables, jamais par une adresse
courriel.
