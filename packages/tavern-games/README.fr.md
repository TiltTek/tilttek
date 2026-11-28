<!-- phil-dev source-sha256=a75e9dcc1177eee2820c5c5d2fdebd4243caf5fc8661899ae22d1c4a1cf7998f -->
# tilttek/tavern-games

**Des jeux classiques de dés et de cartes pour une table de taverne en ligne, tour par tour : dés et mélanges
équitables, chaque joueur reçoit seulement ses propres cartes, les égalités montrées comme des égalités.**

tilttek/tavern-games joue des jeux traditionnels de taverne, libres de droits, à des tables de `tilttek/game-engine`,
avec des règles rédigées pour cette bibliothèque. Pig et Capitaine, Navire et Équipage sont des jeux de dés ; leurs dés
sont lancés par `tilttek/dice` avec le hasard de la table, de sorte que rejouer une table lance les mêmes dés. Crazy
Eights est un jeu de cartes ; ses cartes sont gardées par `tilttek/card-deck`, mélangées à partir d'une graine tirée de
celle de la table, et chaque joueur voit sa propre main, le dessus de la défausse et le nombre de cartes des autres —
jamais leurs cartes ni l'ordre de la pioche.

Un jeu de taverne garde ses propres tours : un dé qui montre 1 ou une carte jouée termine un tour selon les règles du
jeu. Le délai d'un tour passe par un port facultatif : une application qui fournit un minuteur de tour est informée de
qui commence son tour et rappelle quand le délai s'écoule, et le jeu termine ce tour comme le disent ses règles, jamais
avec une pénalité au-delà du tour. Sans minuteur, un tour dure jusqu'à ce qu'il soit joué. Un estimateur compte les
chances d'une position sur des fins simulées, avec les victoires, les égalités et les défaites à part et le nombre de
fins sur lesquelles elles ont été comptées, de sorte qu'une victoire partagée est montrée pour ce qu'elle est. La fin
d'une partie se raconte sur l'un de trois tons : une victoire, une victoire partagée — toutes deux célébrées — ou une
belle partie, racontée avec bienveillance et jamais moquée.

Le paquet s'adresse à une application qui héberge des jeux joués à distance — une soirée de jeux, la taverne d'un
site — et veut chaque table vérifiable et équitable. C'est une bibliothèque PHP simple dont les dépendances sont
`tilttek/game-engine`, `tilttek/dice` et `tilttek/card-deck`. Le jeu d'un éditeur n'est publié qu'avec le consentement de
l'éditeur au dossier ; la bibliothèque n'en détient aucun.

## État

| Partie | État |
|---|---|
| `Contract\TavernGame`, `Turns` : des jeux au tour par tour qui gardent leurs propres tours et acceptent le délai d'un tour | Implémenté et testé |
| `TavernTables`, `Contract\TurnTimer`, `Store\NullTurnTimer`, `Store\InMemoryTurnTimer` : des tables ouvertes et jouées, le minuteur de tour informé, les délais tardifs ignorés | Implémenté et testé |
| `Ending`, `Tone` : une victoire, seule ou partagée, célébrée ; toute autre fin racontée avec bienveillance | Implémenté et testé |
| `DiceCup` : des dés à six faces lancés par `tilttek/dice` avec le hasard d'un coup | Implémenté et testé |
| `Odds\Estimator`, `Odds\Estimate`, `Odds\Trial`, `Contract\Simulation` : des chances sur des fins simulées, égalités à part | Implémenté et testé |
| `Game\Pig`, `Game\ShipCaptainCrew`, `Odds\CargoChase` : deux jeux de dés, et les chances d'un tour du second | Implémenté et testé |
| `Game\CrazyEights` : un jeu de cartes sur `tilttek/card-deck` | Implémenté et testé |
| `TavernGames` : les jeux d'une application, par nom | Implémenté et testé |
| Des exceptions sous une seule interface `TavernGamesException` | Implémenté et testé |
| Des jeux en temps réel, un bundle Symfony, ses contrôleurs et ses contrôleurs Stimulus | Non commencé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Tables et tours](doc/fr/tables-and-turns.md) | `TavernTables`, les tours propres au jeu, le minuteur de tour et les délais, les fins. |
| [Écrire un jeu de taverne](doc/fr/writing-a-tavern-game.md) | Le contrat `TavernGame`, les tours, les dés, les cartes et les règles des jeux de la bibliothèque. |
| [Les jeux de dés](doc/fr/dice-games.md) | Pig et Capitaine, Navire et Équipage : leurs coups, options, vues et issues. |
| [Crazy Eights](doc/fr/crazy-eights.md) | Le jeu de cartes, ses cartes sur `tilttek/card-deck`, ce que voit chaque siège, et ses fins. |
| [Chances](doc/fr/odds.md) | L'estimateur, victoires, égalités et défaites à part, et la poursuite d'une cargaison. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `TavernGamesException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et les bancs d'essai de la bibliothèque, et comment tester un jeu de taverne. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\GameEngine\{Move, Tables};
use Tilttek\TavernGames\{TavernGames, TavernTables};
use Tilttek\TavernGames\Game\{CrazyEights, Pig, ShipCaptainCrew};

$games = new TavernGames(new Pig(), new ShipCaptainCrew(), new CrazyEights());
$tavern = new TavernTables(new Tables($games->games(), $logStore), $games, $turnTimer);

$tavern->open('crazy-eights', 'table:7', ['seat:ann', 'seat:bob', 'seat:cid']);
$tavern->view('table:7', 'seat:ann');               // Ann's hand, the top card, the others' counts
$tavern->play('table:7', new Move(CrazyEights::DRAW, 'seat:ann'), expected: 0);
$tavern->timeout('table:7', 'seat:ann', 1);           // the timer ran out: Ann passes
$tavern->ending('table:7', 'seat:bob')?->tone;        // Victory, Tie or GoodGame once it is over
```

## Prérequis

PHP 8.5 ou une version plus récente, `ext-sodium`, `tilttek/game-engine` 0.1, `tilttek/dice` 0.3 et
`tilttek/card-deck` 0.1.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, faisant affaire sous le nom Le Gnome Archiviste ; un projet Tilttek. Voir [LICENSE](LICENSE).
