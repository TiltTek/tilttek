<!-- phil-dev source-sha256=0bafa3d262d9d6122b14837ab1664c4af2da7b1889791ae0f1ad1fb38c0cb236 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les horloges | Implémentez `Contract\ClockStore`, et passez-le à `TurnClock`. |
| Agir sur ce qui s'est passé | Implémentez `Contract\ClockObserver`, et passez-le à `TurnClock`. |
| Donner l'heure | Passez une `ClockInterface` PSR-20. |
| Agir quand un tour manque de temps | Planifiez `TurnClock::check()` à `ClockView::$expiresAt`. |
| Dire qui peut mettre en pause, ajuster ou arrêter | Vérifiez-le dans l'application avant d'appeler `TurnClock`. |

## Le stockage

`Contract\ClockStore` garde les horloges, chacune à une version :

- `clock()` en donne une par son identifiant, ou null ;
- `save()` en garde une à la version suivante et la retourne. Une à la version 0 est nouvelle, et le stockage n'a aucune
  horloge de son identifiant ; toute autre n'est gardée que si le stockage la détient à cette version, et
  `ClockConflict` est levée sinon, rien n'étant gardé.

Deux pressions d'un même joueur en même temps lisent toutes deux la version 7 : la première sauvegarde garde la
version 8, et la seconde est refusée avec `ClockConflict` ; une fois relu, le joueur ne détient plus le tour.

`Store\InMemoryClockStore` les garde en mémoire, tant que l'objet dure : il sert aux tests. Une application les garde
dans sa base de données. Une horloge contient des chaînes, des entiers, des booléens, un moment, son contrôle du temps —
deux entiers et deux énumérations, dont les valeurs sont leurs cas en chaîne — et ses joueurs, chacun des chaînes, des
entiers et jusqu'à 8 vacances de deux moments.

## L'observateur et ses événements

`Contract\ClockObserver::notify()` est informé de chaque fait une fois l'horloge gardée, par identifiants seulement :

| Événement | Quand | Porte |
|---|---|---|
| `Event\ClockStarted` | Une horloge a démarré. | |
| `Event\TurnStarted` | Le tour d'un joueur a commencé. | le joueur |
| `Event\TurnEnded` | Le tour d'un joueur a pris fin. | le joueur, les millisecondes comptées |
| `Event\TimeExpired` | Le budget d'un joueur s'est épuisé. | le joueur |
| `Event\ClockPaused` | Une horloge a été mise en pause. | |
| `Event\ClockResumed` | Une horloge tourne de nouveau. | |
| `Event\VacationDeclared` | Un joueur a déclaré des vacances. | le joueur |
| `Event\TimeAdjusted` | Du temps a été ajouté ou retiré. | le joueur, les millisecondes |
| `Event\ClockStopped` | Une horloge a été arrêtée, ou tous les joueurs ont manqué de temps. | |

Chaque événement implémente `Event\ClockEvent`, dont `clock` est un identifiant ; les événements d'un joueur
implémentent `Event\PlayerEvent`, qui ajoute `player`. Quand un tour passe, `TimeExpired` vient d'abord si le budget
s'est épuisé, puis `TurnEnded`, puis `TurnStarted` ou `ClockStopped`. Une application envoie un message à partir d'eux
— par Messenger, avec les identifiants seuls — et lit le reste dans le stockage : une poussée de la vue à la table, un
avis au joueur dont le tour commence dans une partie par correspondance.

## L'horloge et les vérifications

`TurnClock` lit l'heure de la `ClockInterface` PSR-20 qu'on lui donne, et de rien d'autre. Entre deux appels, rien
n'arrive de soi-même : le moment où un tour manque de temps est `ClockView::$expiresAt`, et une application appelle
`check()` à ce moment — par un message Messenger différé portant l'identifiant de l'horloge, ou par une tâche
planifiée — pour que l'expiration soit signalée et que, sous `Overtime::Forfeit`, le tour passe au suivant. Une
vérification faite trop tôt, ou deux fois, ne change rien.
