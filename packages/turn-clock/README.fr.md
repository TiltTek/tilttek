<!-- phil-dev source-sha256=c9b1688bf079cc4ccacbe3e03bae0409dbd4ca094a6ca6f4b0e30dd2cf0adee1 -->
# tilttek/turn-clock

**Une minuterie de tour pour la table : budgets par joueur, incréments Fischer ou Bronstein, délais, jours par coup, pauses, vacances et temps supplémentaire.**

tilttek/turn-clock tient le temps d'un jeu au tour par tour, comme une pendule d'échecs le fait pour deux joueurs et une
horloge de jeu pour une table de 20 au plus. Chaque joueur a un budget, le même pour tous ou un par joueur pour
équilibrer la table ; l'horloge tourne pour le joueur dont c'est le tour, et passer le tour le facture.

- **Contrôles du temps.** Un incrément ajouté après chaque tour (Fischer), le temps dépensé rendu jusqu'à concurrence de
  l'incrément (Bronstein), un délai avant que le budget ne coure (délai simple), ou le budget entier de nouveau à chaque
  tour — des jours par coup, comme dans une partie par correspondance.
- **Temps supplémentaire.** Quand un budget s'épuise, l'horloge compte le temps dépassé, sous zéro, pour que la table
  décide ; ou le joueur est hors temps, son budget reste à zéro et ses tours sont sautés, jusqu'à ce que l'horloge
  s'arrête quand tous les joueurs sont hors temps.
- **Pauses et vacances.** La table met l'horloge en pause et la reprend ; un joueur déclare des vacances, durant
  lesquelles son horloge ne tourne pas.
- **L'ordre des tours.** Le joueur suivant dans l'ordre des places qui n'est pas hors temps, ou le joueur nommé ; du
  temps ajouté ou retiré à la main.

Le service lit l'heure d'une horloge PSR-20 et de rien d'autre. Il dit quand le tour en cours manque de temps, pour que
la tâche planifiée de l'application vérifie l'horloge à ce moment. Les faits d'une horloge — démarrée, un tour commencé
ou terminé, le temps expiré, la pause, la reprise, des vacances déclarées, le temps ajusté, l'arrêt — vont à un
observateur, chacun une seule fois, par identifiants seulement.

C'est une bibliothèque PHP simple : un service, des objets-valeurs, des ports et un stockage gardé en mémoire pour les
tests.

## État

| Partie | État |
|---|---|
| `TimeControl`, `Mode`, `Overtime` : budgets, incréments, délais, jours par coup, temps supplémentaire compté ou perdu | Implémenté et testé |
| `TableClock`, `PlayerClock`, `Vacation` : une horloge et le temps de ses joueurs, bornés et vérifiés | Implémenté et testé |
| `TurnClock` : créer, démarrer, passer, vérifier, mettre en pause, reprendre, vacances, ajuster, arrêter | Implémenté et testé |
| `View\ClockView` et `View\PlayerTime` : l'horloge à montrer à la table, et le moment où le tour s'épuise | Implémenté et testé |
| `Store\InMemoryClockStore` : le stockage des tests | Implémenté et testé |
| Un bundle Symfony : le stockage Doctrine, les vérifications planifiées, Mercure et un affichage Stimulus | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Horloges et contrôles du temps](doc/fr/clocks.md) | Les budgets, les modes, le temps supplémentaire, les tours, les pauses, les vacances, les ajustements, l'arrêt et la vue. |
| [Points d'extension](doc/fr/extension-points.md) | Le stockage, l'observateur et ses événements, l'horloge, et la vérification à l'expiration. |
| [Exceptions](doc/fr/exceptions.md) | `TurnClockException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\TurnClock\Mode;
use Tilttek\TurnClock\TimeControl;
use Tilttek\TurnClock\TurnClock;

$clocks = new TurnClock($store, $clock, $observer);              // your ClockStore, a PSR-20 clock, an observer

$clocks->create('table-4', ['player-1', 'player-2', 'player-3'],
    new TimeControl(10 * 60_000, Mode::Fischer, 5_000),           // ten minutes each, five seconds added per turn
    ['player-3' => 15 * 60_000],                                  // fifteen minutes for a new player
);
$clocks->start('table-4');

$clocks->pass('table-4', 'player-1');                             // player 1 ends their turn; player 2's runs
$clocks->pause('table-4');                                        // a break: no time counts
$clocks->resume('table-4');

$view = $clocks->view('table-4');                                 // send $view->toArray() to the table
// schedule $clocks->check('table-4') at $view->expiresAt

$clocks->create('mail-game', ['player-1', 'player-2'], TimeControl::daysPerTurn(3));   // three days per move
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `psr/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
