<!-- phil-dev source-sha256=f64928fd2f6c66804607fb5fcc6dc60298be42b2962dc069566e6ed241da7897 -->
# Horloges et contrôles du temps

Chaque durée est en millisecondes.

## Un contrôle du temps

`TimeControl` contient :

- **le budget** de chaque joueur, de 1 seconde à 31 jours ;
- **le mode**, un `Mode`, et **l'incrément**, de 0 à 1 jour :

  | Mode | Ce que fait l'incrément |
  |---|---|
  | `Mode::Fischer` | Il est ajouté au budget après chaque tour. |
  | `Mode::Bronstein` | Le temps dépensé dans un tour est rendu après celui-ci, jusqu'à concurrence de l'incrément. |
  | `Mode::Delay` | Le budget ne court qu'une fois l'incrément écoulé dans chaque tour. |
  | `Mode::PerTurn` | Inutilisé : chaque tour a de nouveau le budget entier, et rien ne se reporte. |

- **le temps supplémentaire**, un `Overtime` : `Overtime::Continue` compte le temps dépassé sous zéro, et la table
  décide ; `Overtime::Forfeit` met le joueur hors temps, son budget à zéro, ses tours sautés.

`TimeControl::daysPerTurn(3)` est le contrôle d'une partie par correspondance : trois jours par coup, chaque tour ayant
de nouveau les trois jours, un joueur qui les épuise étant hors de la partie.

## Une horloge

`TurnClock::create()` fabrique une horloge pour 1 à 20 joueurs, dans l'ordre des places, chacun avec le budget du
contrôle ou le budget donné pour lui — un handicap, un nouveau joueur à qui l'on donne plus de temps. Elle n'est pas
démarrée. Les identifiants comptent de 1 à 128 lettres, chiffres, `_`, `.`, `:` ou `-`, commencent par une lettre, et ne
sont jamais un nom ni une adresse.

`start()` lance le tour du premier joueur : le premier dans l'ordre des places, ou celui qui est nommé, qui ne doit pas
être hors temps.

## Les tours

`pass()` termine le tour du joueur qui le détient : l'appel le nomme, pour que deux pressions ne passent jamais deux
tours. Le temps compté dans le tour est facturé — sous `Mode::Delay`, seulement ce qui dépasse le délai — et l'incrément
donné, selon le mode. Puis le tour suivant commence : le joueur nommé, qui est un autre joueur et n'est pas hors temps,
sinon le suivant dans l'ordre des places, les places bouclant, en sautant les joueurs hors temps. Quand tous les autres
joueurs sont hors temps, le même joueur continue ; quand tous les joueurs le sont, l'horloge s'arrête. Sous
`Mode::PerTurn`, le budget du joueur suivant est de nouveau entier.

Une `PlayerClock` garde ce qui restait du budget d'un joueur au début de son tour, ses tours terminés et le temps qu'il
a dépensé. `TableClock::remaining()` donne ce qui reste à un moment, le tour en cours compté.

## Manquer de temps

Le moment où un tour manque de temps est celui de `ClockView::$expiresAt`. Une application planifie `check()` à ce
moment — ou l'appelle à chaque affichage — et `check()` agit quand le budget est dépensé :

- sous `Overtime::Continue`, `TimeExpired` est informé, une fois par tour, et le tour continue, le budget passant sous
  zéro ;
- sous `Overtime::Forfeit`, `TimeExpired` est informé et le tour passe au suivant, comme le fait `pass()`, le joueur
  étant hors temps.

Un tour terminé après l'épuisement de son budget informe `TimeExpired` avec lui, sauf si `check()` l'a déjà fait. Sous
`Overtime::Continue`, un joueur qui commence un tour sous zéro n'est pas informé de nouveau : son incrément peut le
ramener au-dessus.

## Pauses

`pause()` arrête l'horloge pour toute la table : le temps compté jusqu'ici dans le tour est gardé, et aucun temps ne
compte avant `resume()`. Un tour passé pendant la pause commence en pause. Mettre en pause une horloge en pause, ou
reprendre une horloge qui tourne, ne change rien.

## Vacances

`declareVacation()` enregistre une `Vacation` d'un joueur, d'au plus 60 jours : tant qu'elle dure, son horloge ne tourne
pas, dans son tour ou dans un tour à venir. Un joueur garde au plus 8 vacances, sans chevauchement ; celles qui sont déjà
finies sont oubliées, et une vacance déclarée une fois finie est refusée. Une vacance déjà commencée compte à partir du
moment où elle est déclarée : le temps que le tour en cours a compté avant reste compté. `ClockView::$expiresAt` saute
les vacances à venir du joueur courant.

## Ajustements

`adjust()` ajoute du temps au budget d'un joueur, ou en retire avec un delta négatif, d'au plus 31 jours dans un sens
comme dans l'autre. Sous `Overtime::Forfeit`, un budget ne descend pas sous zéro, et un joueur hors temps à qui l'on
rend du temps joue de nouveau. Un joueur courant ramené au-dessus de zéro par cet ajustement peut être informé de
`TimeExpired` de nouveau.

## L'arrêt

`stop()` facture le temps du tour en cours, sans terminer le tour ni donner l'incrément, et arrête l'horloge : elle ne
change plus. L'horloge se souvient de qui détenait le tour.

## La vue

`view()` donne une `ClockView`, dont le `toArray()` est envoyé à la table :

- l'horloge, le joueur qui détient le tour, si elle est en pause ou arrêtée, la version lue ;
- `expiresAt`, le moment où le tour en cours manque de temps — maintenant, s'il en manque déjà — ou null quand l'horloge
  ne tourne pas ;
- un `PlayerTime` par joueur, dans l'ordre des places : ce qui reste, sous zéro en temps supplémentaire, le temps
  supplémentaire, les tours terminés, le temps dépensé, et si son horloge tourne, s'il est hors temps ou en vacances.
