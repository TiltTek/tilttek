<!-- phil-dev source-sha256=4c80bb0e7cf2bc061c2e041648c9f18ccf489b6d15a1a2d764145b2d70c52a2e -->
# tilttek/mini-games

**De petits jeux joués seul sur un site, avec un jeu quotidien que tout le monde partage, des résultats partagés sans
divulgâcher, et aucune série qui punit une journée manquée.**

tilttek/mini-games est la base des mini-jeux d'un site, construite sur `tilttek/game-engine`. Un mini-jeu est un jeu du
moteur joué à une table d'un seul siège, qui déclare aussi dans quel sens vont ses scores — le plus de points ou le
moins d'essais —, les touches qui le jouent et la façon de partager un résultat. Le jeu quotidien donne à tous les
joueurs d'une journée le même jeu : sa graine est un HMAC du jeu et de la journée sous une clé dérivée d'un secret du site,
de sorte que personne ne peut deviner le jeu de demain, et la graine est montrée, pour que chacun vérifie les tirages,
une fois la journée terminée. Un résultat est partagé sous forme de grille de texte — carrés colorés ou caractères simples — qui
dit comment chaque étape s'est passée et jamais ce qu'elle était.

La fin d'une partie se raconte sur l'un de deux tons : une victoire, que l'application célèbre, ou une partie terminée,
racontée avec bienveillance et jamais moquée. Un record est établi par un meilleur score seulement, jamais par un score
égal. L'historique d'un joueur compte les jours joués, les victoires, le meilleur score et la répartition des scores ; il
ne compte aucune série, si bien qu'une journée manquée ne coûte rien. Chaque jeu se joue au clavier seul, et aucun n'a
besoin d'animation. Le score de chaque jeu quotidien terminé va à un port, qu'une application relie à ses classements ou
à son journal d'activité.

Deux jeux sont fournis comme modèles, tous deux des jeux traditionnels au crayon et au papier : Taureaux et Vaches, où
moins il y a d'essais mieux c'est, et Plus ou Moins, où plus il y a de bons choix mieux c'est.

Le paquet s'adresse à une application qui offre de petits jeux, quotidiens ou non, et les veut équitables, partageables
et doux. C'est une bibliothèque PHP simple dont l'unique dépendance est `tilttek/game-engine`.

## État

| Partie | État |
|---|---|
| `Contract\MiniGame`, `Scoring`, `ScoreOrder`, `Control` : un jeu du moteur qui déclare ses scores et ses touches | Implémenté et testé |
| `DailySeed`, `Day`, `DailyGames` : le jeu quotidien, le même pour tous les joueurs d'une journée, sa graine montrée une fois la journée terminée | Implémenté et testé |
| `ShareGrid`, `Cell`, `CellSymbols` : un résultat partagé en texte sans divulgâcher | Implémenté et testé |
| `Ending`, `Tone` : une victoire célébrée, toute autre fin racontée avec bienveillance ; des records établis par de meilleurs scores seulement | Implémenté et testé |
| `DailyScore`, `DailyHistory`, `Contract\ScoreSink`, `Store\NullScoreSink`, `Store\InMemoryScoreSink` : des scores envoyés à un port, et un historique sans série | Implémenté et testé |
| `MiniGames` : les jeux d'une application, par nom | Implémenté et testé |
| `Game\BullsAndCows`, `Game\HigherOrLower`, `Game\Code` : deux jeux d'exemple | Implémenté et testé |
| Des exceptions sous une seule interface `MiniGameException` | Implémenté et testé |
| Un bundle Symfony, ses contrôleurs, ses gabarits et ses contrôleurs Stimulus | Non commencé : des adaptateurs de l'application, ou d'un futur bundle |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Écrire un mini-jeu](doc/fr/writing-a-mini-game.md) | Le contrat `MiniGame`, le pointage, les commandes, le partage et le jeu sans animation. |
| [Jeux quotidiens](doc/fr/daily-games.md) | La graine du jour, la table du joueur, le puits de scores et la vérification du jeu d'une journée. |
| [Fins, records et historique](doc/fr/endings-and-history.md) | Le ton d'une fin, la célébration, les records et un historique sans série. |
| [Partager un résultat](doc/fr/sharing.md) | La grille, ses cellules, ses symboles et ce qu'elle ne dit jamais. |
| [Les jeux d'exemple](doc/fr/example-games.md) | Taureaux et Vaches et Plus ou Moins, leurs coups, leurs vues et leurs partages. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `MiniGameException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests, la cible de fuzzing et le banc d'essai de la bibliothèque, et comment tester un mini-jeu. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\MiniGames\{DailyGames, DailySeed, Day, MiniGames};
use Tilttek\MiniGames\Game\{BullsAndCows, HigherOrLower};

$games = new MiniGames(new BullsAndCows(), new HigherOrLower());
$daily = new DailyGames(new Tables($games->games(), $logStore), $games, new DailySeed($secret), $scoreSink);
$today = Day::of(new DateTimeImmutable(), new DateTimeZone('America/Toronto'));

$daily->start('higher-or-lower', $today, 'u-101');
$daily->play('higher-or-lower', $today, 'u-101', HigherOrLower::CALL, ['call' => 'higher']);
$daily->view('higher-or-lower', $today, 'u-101');                       // the numbers shown so far
$daily->share('higher-or-lower', $today, 'u-101')->toText('Higher or Lower 12');
$daily->ending('higher-or-lower', $today, 'u-101')?->tone->celebrates(); // true for a victory only
```

## Prérequis

PHP 8.5 ou une version plus récente, et `tilttek/game-engine` 0.1.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, faisant affaire sous le nom Le Gnome Archiviste ; un projet Tilttek. Voir [LICENSE](LICENSE).
