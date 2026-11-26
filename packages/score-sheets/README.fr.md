<!-- phil-dev source-sha256=4b05cfa40775abfd8df12f984ca165db452d496bd3321db3cdc4d2964044f515 -->
# tilttek/score-sheets

**Des feuilles de pointage déclarées par jeu, avec des catégories, des totaux calculés en direct et des bris d'égalité,
une feuille vierge à imprimer, et des résultats transmis au journal de jeu.**

tilttek/score-sheets tient le pointage d'un jeu de société comme le fait sa feuille, à partir d'une déclaration qui est
une donnée, pas du code : les catégories dans l'ordre, chacune saisie par le pointeur entre ses bornes ou calculée à
partir de celles qui la précèdent — une somme, un produit, des points selon la place pour une majorité, une prime
conditionnelle simple —, lesquelles comptent dans le total, et les bris d'égalité. Le tableau des scores est recalculé à
chaque saisie : chaque valeur, chaque total, les rangs, et les étapes qui expliquent chaque valeur ; les scores
d'équipe se composent des totaux de leurs membres, avec le détail de la somme.

La feuille d'une partie est préparée avant que les joueurs la voient, publiée, remplie, puis fermée ; une feuille
fermée trop tôt est rouverte, corrigée et fermée de nouveau, comme une nouvelle révision. Une partie fermée donne un
résultat neutre — places, totaux, équipes, révision — et un message qui ne porte que son identifiant, qu'un journal de
jeu ou un classement peut lire sans dépendre de l'un ni de l'autre. Une feuille vierge à imprimer se présente en
données neutres : la mise en page, en HTML ou en PDF, revient à l'application.

Il s'adresse à une application qui tient les scores à la table — une soirée de jeux, un tournoi, le journal de jeu d'un
club. C'est une bibliothèque PHP simple, sans dépendance.

## État

| Partie | État |
|---|---|
| `SheetDefinition`, `Category`, `Tiebreaker` : des déclarations en données, lues depuis JSON et réécrites | Implémenté et testé |
| `Operation\*` : somme, produit, majorité et prime, et leur codec | Implémenté et testé |
| `Entries`, `Team` : les participants, les équipes, et les valeurs saisies, corrigées ou effacées | Implémenté et testé |
| `Scoring\*` : totaux, rangs avec bris d'égalité, explications et scores d'équipe | Implémenté et testé |
| `ScoreKeeper`, `MatchSheet`, `SheetStatus` : préparer, publier, saisir, fermer et rouvrir la feuille d'une partie | Implémenté et testé |
| `MatchResult`, `Placement`, `Message\MatchResultRecorded` : le résultat transmis | Implémenté et testé |
| `BlankSheet` : la feuille vierge à imprimer, en données neutres | Implémenté et testé |
| `Contract\MatchSheetStore`, `Store\InMemoryMatchSheetStore` : le port du magasin, et le magasin en mémoire pour les tests | Implémenté et testé |
| Un magasin sur Doctrine DBAL, la mise en page HTML ou PDF d'une feuille, un bundle Symfony | Non commencé : des adaptateurs de l'application, ou d'un futur bundle |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Déclarer une feuille](doc/fr/declaring-sheets.md) | Les catégories, les opérations, les bris d'égalité et les équipes, en JSON ou en code. |
| [Pointage](doc/fr/scoring.md) | Les saisies, les totaux, les rangs, les explications et les scores d'équipe. |
| [Feuilles de partie](doc/fr/match-sheets.md) | Préparer, publier, fermer et rouvrir une feuille ; les versions ; le magasin. |
| [Transmettre les résultats](doc/fr/results.md) | Le résultat neutre et son message, pour un journal de jeu ou un classement. |
| [Imprimer une feuille vierge](doc/fr/printing.md) | La feuille vierge en données neutres. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `ScoreSheetsException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\ScoreSheets\Entries;
use Tilttek\ScoreSheets\MatchId;
use Tilttek\ScoreSheets\ParticipantId;
use Tilttek\ScoreSheets\ScoreKeeper;
use Tilttek\ScoreSheets\SheetDefinition;

$sheet = SheetDefinition::fromJson(file_get_contents('sheets/lantern-harbor.json'));
$keeper = new ScoreKeeper($store); // your Contract\MatchSheetStore
$match = new MatchId('m-2031');
$ada = new ParticipantId('u-101');
$bo = new ParticipantId('u-102');

$keeper->prepare($match, $sheet, new Entries([$ada, $bo]));
$keeper->publish($match, 0);

$board = $keeper->record($match, 1, $ada, 'boats', 5);   // the scoreboard, computed again
echo $board->participant($ada)->total;

$result = $keeper->close($match, 2);
$bus->dispatch($result->message());                      // the match and the revision only
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
