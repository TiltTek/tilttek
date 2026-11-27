<!-- phil-dev source-sha256=9189f82b5ea8cb58bf564e43a36c66c7ba93b7d0b2d3fd6bbcbce29c81c4d82d -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/turn-clock` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `TimeControl`, `Mode` et `Overtime` : un budget d'une seconde à 31 jours, un incrément d'au plus un jour ajouté après
  chaque tour (Fischer), rendu jusqu'au temps passé (Bronstein) ou attendu avant que le budget s'écoule (un délai), ou
  le budget entier de nouveau à chaque tour, et le temps dépassé compté sous zéro ou perdu par forfait ;
  `TimeControl::daysPerTurn()` pour une partie par correspondance.
- `TableClock`, `PlayerClock` et `Vacation` : une horloge de 1 à 20 joueurs dans l'ordre des places, chacun avec son
  budget, ce qui reste, ses tours, le temps passé et jusqu'à 8 vacances d'au plus 60 jours, bornée et vérifiée.
- `TurnClock` : une horloge créée avec un budget par joueur, lancée, et lue dans une horloge PSR-20 ; elle ne garde
  aucun état entre les appels.
- `TurnClock::pass()` : le tour terminé en nommant le joueur qui le tient, le temps imputé et l'incrément donné, le tour
  suivant au joueur nommé ou au suivant dans l'ordre des places qui n'est pas à court de temps, l'horloge arrêtée quand
  chaque joueur est à court.
- `TurnClock::check()` : l'expiration du tour en cours annoncée une seule fois en temps dépassé, ou le tour passé au
  suivant quand l'horloge déclare forfait.
- `TurnClock::pause()` et `resume()` : l'horloge de toute la table arrêtée et relancée, un tour passé pendant la pause
  commençant en pause.
- `TurnClock::declareVacation()` : les vacances d'un joueur, pendant lesquelles son horloge ne court pas, comptées à
  partir du moment de leur déclaration quand elles ont déjà commencé.
- `TurnClock::adjust()` et `stop()` : du temps ajouté ou retiré, et l'horloge arrêtée avec le tour en cours imputé.
- `View\ClockView` et `View\PlayerTime` : le temps de chaque joueur à un moment, et le moment où le tour en cours sera à
  court de temps, les vacances à venir sautées, en données simples.
- Les événements `ClockStarted`, `TurnStarted`, `TurnEnded`, `TimeExpired`, `ClockPaused`, `ClockResumed`,
  `VacationDeclared`, `TimeAdjusted` et `ClockStopped`, par identifiants seulement, annoncés une seule fois à un
  `Contract\ClockObserver`.
- `Contract\ClockStore`, gardé à une version, et `Store\InMemoryClockStore` pour les tests.
- Les exceptions `InvalidClock`, `ClockRefused`, `ClockNotFound` et `ClockConflict`, sous `TurnClockException`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
