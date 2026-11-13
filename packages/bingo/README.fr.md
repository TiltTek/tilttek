<!-- phil-dev source-sha256=38723e6313d47e9badc014b05718bc708303e95836e6d6788d4922335f101837 -->
# tilttek/bingo

**Cartons de bingo pour un congrès ou une séance : des cartons uniques tirés d'une réserve par un germe du serveur, des
cases cochées par des gestes enregistrés ou appelées par l'animateur, des motifs gagnants, des victoires vérifiées par
le serveur, des lots de cartons imprimés dont les étiquettes sont vérifiées par le serveur, des mises à jour en direct
par Mercure, ainsi que les événements et le crochet Stimulus d'une célébration.**

Un jeu tire ses cartons d'une réserve de cases : « assister à une conférence », « visiter le kiosque de dés »,
« obtenir un 20 naturel ». Chaque joueur reçoit un carton bien à lui, différent de tous les autres cartons du jeu, tiré
d'un secret du serveur seul : le germe du carton `n` est le HMAC-SHA256 du jeu et du numéro de série sous le secret, si
bien que le même carton se tire de nouveau à partir de son numéro de série — un carton imprimé se vérifie par son
numéro — et qu'aucun joueur ne peut deviner les cartons des autres ni choisir le sien.

Le carton d'un congrès se coche par ce que fait le joueur : chaque case nomme le geste qui la coche, par un verbe et
peut-être un objet, et le serveur coche le carton à mesure que le journal d'activité de `tilttek/activity` enregistre
les gestes, puis le décoche quand un geste est annulé. Un motif complété gagne de lui-même. Le carton d'une séance est
coché par l'animateur, qui appelle des cases pour tous les cartons à la fois ; un carton gagne quand son joueur le
réclame et que le serveur trouve un motif parmi les cases appelées. Dans les deux cas, le joueur ne coche jamais un
carton ni ne déclare une victoire : le serveur vérifie le carton d'après son germe et ses cases cochées. Les cartons
d'une séance peuvent aussi être imprimés par lots : chacun porte une étiquette, son numéro de série et un code de
vérification tiré du secret, que l'organisateur saisit pour vérifier le carton de celui qui crie.

Les motifs sont une ligne, une colonne, une diagonale, le carton plein, les quatre coins, ou ceux de l'application. Une
victoire envoie `BingoWon` ; le gabarit du carton et son contrôleur Stimulus la transforment en événement du DOM
`bingo:won`, sur lequel joue la célébration du kit PHIL UX ; les appels, les victoires et les cases cochées parviennent aux écrans
des joueurs par Mercure. C'est une bibliothèque PHP ordinaire dont les dépendances sont `tilttek/activity`,
`psr/clock` et `psr/event-dispatcher` ; `symfony/mercure` n'est nécessaire que pour les mises à jour en direct.

## État

| Partie | État |
|---|---|
| `Bingo` : les jeux, la distribution, l'appel, les gestes enregistrés et annulés, les réclamations et les vérifications | Implémenté et testé |
| `BingoGame`, `Card`, `TickMode`, `Claim` | Implémentés et testés |
| `CardDealer` : les dispositions tirées du secret du serveur, les empreintes | Implémenté et testé |
| Lots imprimés : `Bingo::print()`, `SerialLabel`, `Bingo::verifyLabel()`, `View\PrintedCardView` et `templates/print.html.twig` | Implémentés et testés |
| `Mercure\BingoPublisher` : les appels, les victoires et les cases cochées envoyés par Mercure | Implémenté et testé |
| `Square`, `SquarePool`, `Trigger`, `Grid` | Implémentés et testés |
| `WinPattern`, `WinPatterns` : ligne, colonne, diagonale, carton plein, quatre coins, personnalisé | Implémentés et testés |
| `Activity\BingoActivityObserver` : les cartons cochés d'après le journal d'activité | Implémenté et testé |
| Événements : `BingoWon`, `SquareTicked`, `SquareCalled` | Implémentés et testés |
| `View\CardView`, `View\CellView`, `templates/card.html.twig` et le contrôleur Stimulus `card` | Implémentés et testés |
| `Contract\BingoStore` avec versions, et son dépôt en mémoire | Implémentés et testés |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Mener un jeu](doc/fr/running-a-game.md) | Les réserves, les grilles, les motifs, la distribution, les cases cochées, l'appel et la victoire. |
| [Les cartons et leur germe](doc/fr/cards-and-seeds.md) | Comment un carton est tiré, gardé unique et vérifié. |
| [Cartons imprimés](doc/fr/printing.md) | Les lots à imprimer, leurs étiquettes et leur vérification. |
| [Mises à jour en direct](doc/fr/live-updates.md) | Les appels, les victoires et les cases cochées envoyés par Mercure. |
| [Célébrations](doc/fr/celebrations.md) | Les événements, le gabarit du carton et son contrôleur Stimulus. |
| [Stockage](doc/fr/storing.md) | Le contrat du dépôt, les versions et les cases cochées en même temps. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `BingoException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests et les bancs d'essai de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\Bingo\{Bingo, CardDealer, Grid, Square, SquarePool, Trigger, WinPatterns};
use Tilttek\Bingo\Activity\BingoActivityObserver;
use Tilttek\Bingo\Store\InMemoryBingoStore;

$bingo = new Bingo(new InMemoryBingoStore(), new CardDealer($secret), $dispatcher, $clock);

$grid = new Grid(5);                                      // 5 × 5, free centre
$bingo->create('con-2026', new SquarePool([
    new Square('talk', 'Attend a talk', new Trigger('talk.attended')),
    new Square('dice-booth', 'Visit the dice booth', new Trigger('booth.visited', 'booth:dice')),
    // ... at least 24 squares
]), $grid, WinPatterns::line($grid));

$card = $bingo->deal('con-2026', 'member:44');           // the same card on every call
$journalObservers[] = new BingoActivityObserver($bingo); // gestures tick, a line wins: BingoWon
```

## Prérequis

PHP 8.5 ou une version plus récente ; `tilttek/activity` 0.1, `psr/clock` et `psr/event-dispatcher`. Le gabarit exige
Twig, et son contrôleur Stimulus, seulement là où l'application les emploie ; les mises à jour en direct exigent `symfony/mercure`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
