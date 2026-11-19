<!-- phil-dev source-sha256=88e58ccd23a070cc79fc53f1a0867dadc9db07b51552cfa4652275fdfee44dd9 -->
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

L'effacement tire un pseudonyme qui lui est propre — `erased:` et 32 chiffres hexadécimaux aléatoires — et l'écrit à la
place du membre dans les parties, les résultats, les écrans et les réservations ; leurs résultats sont retirés du
classement. Les temps, les indices et les places restent, de sorte que les autres équipes gardent leur rang et les
séances leurs places. Le rapport nomme chaque colonne comme `pseudonymised`.

Une référence du genre `erased` donnée par l'appelant — comme équipe, groupe ou animateur — est refusée par la
bibliothèque avec `InvalidRoom` : seul un effacement en écrit une.

## Ce qui n'est jamais gardé

Les mises à jour de Mercure ne nomment que la partie et le coup. Le message de Messenger de l'horaire, `ExpireDue`, ne
transporte rien. Une équipe, un groupe et un animateur sont nommés par des identifiants stables, jamais par une adresse
courriel.
