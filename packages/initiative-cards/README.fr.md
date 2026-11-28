<!-- phil-dev source-sha256=62e39237e64f1b39257f5ba132e0efdabb5df5d56bff39e20ffe8a061782936b -->
# tilttek/initiative-cards

**L'ordre des tours distribué d'un jeu de cartes à chaque ronde : combattants cachés, vaincus estompés et sautés, joueurs qui terminent eux-mêmes leur tour et nomment qui joue ensuite.**

tilttek/initiative-cards tient l'ordre des tours d'un combat de jeu de rôle comme le fait une table avec un jeu de
cartes. À chaque ronde, le maître de jeu donne une carte à chaque combattant encore debout, et les tours suivent les
cartes : avec le jeu de 52 cartes et ses jokers, un joker d'abord, puis l'as jusqu'au deux, et deux cartes de même
rang selon l'ordre des couleurs — pique, cœur, carreau, trèfle, ou un autre ordre. Un joker mélange de nouveau le jeu
une fois sa ronde terminée, et la défausse est remise dans la pioche chaque fois que celle-ci devient trop courte. Un
combattant peut piger jusqu'à trois cartes et garder la meilleure.

- **Les joueurs terminent eux-mêmes leurs tours.** Le membre qui contrôle le combattant détenant le tour le termine — le
  maître de jeu peut terminer n'importe lequel — et peut nommer qui joue ensuite parmi ceux qui n'ont pas agi, comme
  dans une initiative « popcorn ».
- **Des combattants cachés.** Le maître de jeu cache un combattant : la vue des joueurs l'omet jusqu'à ce qu'il soit
  révélé.
- **Les vaincus s'estompent.** Un combattant vaincu reste dans l'ordre, estompé, et ses tours sont sautés jusqu'à ce
  qu'il se relève.
- **Des combattants vont et viennent.** Celui qui se joint en cours de ronde pige aussitôt et attend après le tour en
  cours, selon sa carte ; celui qui part défausse sa carte.

Les cartes, les jeux et les mélanges sont ceux de `tilttek/card-deck` ; tout jeu convient, avec un classement de ses
faces écrit par l'application. Les faits d'une rencontre — commencée, une ronde distribuée ou terminée, un tour commencé
ou terminé, un combattant qui se joint, part, est vaincu, se relève, est caché ou révélé, le jeu remélangé, la rencontre
terminée — vont à un observateur, chacun une seule fois, par identifiants seulement.

C'est une bibliothèque PHP simple : un service, des objets-valeurs, des ports et un stockage gardé en mémoire pour les
tests.

## État

| Partie | État |
|---|---|
| `Encounter`, `Combatant` : une rencontre, ses piles et ses combattants dans l'ordre des tours, bornés et vérifiés | Implémenté et testé |
| `InitiativeTracker` : commencer, distribuer, terminer un tour et nommer le suivant, joindre, partir, vaincre, relever, cacher, révéler, terminer | Implémenté et testé |
| `StandardRanking` et `Contract\CardRanking` : le jeu de 52 cartes et ses jokers, l'ordre des couleurs, d'autres jeux | Implémenté et testé |
| `View\InitiativeView` et `View\Slot` : ce que voient le maître de jeu et les joueurs | Implémenté et testé |
| `Store\InMemoryEncounterStore` : le stockage des tests | Implémenté et testé |
| Un bundle Symfony : le stockage Doctrine, Messenger, Mercure et les composants Twig | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Rencontres, rondes et tours](doc/fr/encounters.md) | La distribution, le classement, le remélange, les tours, le suivant nommé, l'arrivée, la défaite, les combattants cachés et les vues. |
| [Points d'extension](doc/fr/extension-points.md) | Le stockage, le classement, l'observateur et ses événements, le générateur aléatoire. |
| [Exceptions](doc/fr/exceptions.md) | `InitiativeException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\CardDeck\StandardDeck;
use Tilttek\InitiativeCards\Combatant;
use Tilttek\InitiativeCards\InitiativeTracker;

$tracker = new InitiativeTracker($store, observer: $observer);    // your EncounterStore and InitiativeObserver

$tracker->start('fight-12', 'member-1', StandardDeck::create(jokers: 2), [
    new Combatant('hero-ada', 'member-2'),                        // a character and the player who controls it
    new Combatant('hero-bo', 'member-3', draws: 2),               // draws two cards, keeps the best
    new Combatant('ogre', 'member-1', hidden: true),              // the game master's, unseen by the players
]);

$encounter = $tracker->deal('fight-12', 'member-1');             // round 1: a card each, the turns follow the cards
$current = $encounter->current();                                 // the combatant holding the turn

$tracker->endTurn('fight-12', 'hero-ada', 'member-2', next: 'hero-bo');   // ends Ada's turn, Bo goes next
$tracker->defeat('fight-12', 'ogre', 'member-1');                 // faded, its turns skipped
$view = $tracker->view('fight-12', 'member-3');                   // send $view->toArray() to that player
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `tilttek/card-deck`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
