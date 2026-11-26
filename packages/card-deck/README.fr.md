<!-- phil-dev source-sha256=34e46aacdc9fa7feb53ce1579b602bb25e144f7c535f7cfb7429fa9249686438 -->
# tilttek/card-deck

**Des paquets de cartes pour le jeu en ligne : mélangés à partir d'une graine vérifiable, pigés, distribués et défaussés,
chaque joueur ne recevant que les cartes qu'il a le droit de voir.**

tilttek/card-deck garde les cartes d'une partie sur le serveur. Un paquet est une liste ordonnée de cartes ; chaque
carte se trouve dans une zone — la pioche, la main d'un joueur, la défausse, la table, ou une pile que le jeu nomme —
face visible ou face cachée. Une carte a une identité, distincte de ce qu'elle montre : une face ou plusieurs, et un
dos. Chaque geste à la table est un événement enregistré — mélanger, piger sur le dessus, sous le paquet ou au hasard,
distribuer face cachée ou face visible, défausser, retourner, tourner vers une autre face, déplacer, ramasser une zone
dans une autre — et rejouer le journal d'une partie à partir de sa graine redonne le même état, carte pour carte.

Les mélanges et les pioches au hasard viennent d'une graine de `tilttek/dice` : son engagement est publié à
l'ouverture de la partie, les joueurs ajoutent une graine à eux, et la graine est révélée à la fin de la partie, de
sorte que chacun peut vérifier que le paquet n'a pas été arrangé. Un joueur reçoit une vue de la partie, jamais la
partie : sa propre main, les cartes face visible, le nombre de cartes de chaque zone — et jamais l'ordre de la pioche,
une carte cachée ni la graine.

Il s'adresse à une application qui joue des jeux de cartes en ligne — un jeu de cartes, les cartes d'initiative d'un
jeu de rôle, les jeux d'une soirée de jeux — et qui ne doit pas confier au navigateur ce qu'un joueur n'a pas le
droit de voir. C'est une bibliothèque PHP simple, dont l'unique dépendance est `tilttek/dice`, pour la graine et son
engagement.

## État

| Partie | État |
|---|---|
| `Card`, `Face`, `Deck`, `StandardDeck` : cartes d'une face ou de plusieurs et un dos, paquets d'au plus 1 024 cartes, le jeu de 52 cartes et ses jokers | Implémenté et testé |
| `ZoneId` : la pioche, la défausse, la table, les mains et les piles nommées | Implémenté et testé |
| `Gesture\*` : mélanger, piger, distribuer, défausser, retourner, tourner une face, déplacer, ramasser, et leur codec | Implémenté et testé |
| `DeckState` : l'état d'une partie, ses gestes, sa relecture et son empreinte | Implémenté et testé |
| `Shuffler` : le mélange de Fisher-Yates sur le générateur aléatoire d'une graine | Implémenté et testé |
| `View\*` : ce qu'un joueur ou un spectateur peut voir | Implémenté et testé |
| `CardTable`, `GameRecord`, `Reveal` : ouvrir, démarrer, jouer sur une version, voir, terminer et vérifier une partie | Implémenté et testé |
| `Contract\GameStore`, `Store\InMemoryGameStore` : le port du magasin, et le magasin en mémoire pour les tests | Implémenté et testé |
| Un magasin sur Doctrine DBAL, un bundle Symfony, une poussée des vues par Mercure | Non commencé : des adaptateurs de l'application, ou d'un futur bundle |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Cartes et zones](doc/fr/cards-and-zones.md) | Les cartes, les faces, les paquets, les zones, et une zone vide. |
| [Gestes](doc/fr/gestures.md) | Chaque geste, ce qu'il fait et quand il est refusé ; rejouer un journal. |
| [Équité](doc/fr/fairness.md) | La graine, son engagement, le mélange, les pioches au hasard, et la vérification d'une partie terminée. |
| [Vues des joueurs](doc/fr/player-views.md) | Ce qu'un joueur voit, ce qu'il ne voit jamais, et comment l'envoyer. |
| [Parties et magasins](doc/fr/games-and-stores.md) | `CardTable`, les versions, le port du magasin et l'écriture d'un magasin. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `CardDeckException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\CardDeck\CardTable;
use Tilttek\CardDeck\GameId;
use Tilttek\CardDeck\Gesture\Deal;
use Tilttek\CardDeck\Gesture\Discard;
use Tilttek\CardDeck\PlayerId;
use Tilttek\CardDeck\StandardDeck;
use Tilttek\Dice\Fairness\Seed;

$table = new CardTable($store); // your Contract\GameStore
$game = new GameId('g-7f3a');
$alice = new PlayerId('u-101');
$bob = new PlayerId('u-102');

$commitment = $table->open($game, StandardDeck::create(jokers: 2), Seed::generate(new Randomizer(new Secure())));
// Publish $commitment->hex, then let the players choose their seed.
$table->start($game, $clientSeed);

$table->play($game, 1, new Deal([$alice, $bob], 7));
$view = $table->view($game, $alice);           // send $view->toArray() to Alice, and nothing else

$table->play($game, $view->version, new Discard($cardAliceChose));
$reveal = $table->finish($game);               // publish $reveal->toArray(): anyone can verify the game
```

## Prérequis

PHP 8.5 ou une version plus récente, et `tilttek/dice` 0.3.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
