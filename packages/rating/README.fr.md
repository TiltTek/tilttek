<!-- phil-dev source-sha256=6904ca641880e272d2c311928becdf17bc57ad4b5278a09fe925b0d4b14b0579 -->
# tilttek/rating

**Des cotes par jeu pour les joueurs, tirées de leurs résultats : Elo avec un K décroissant, OpenSkill avec
Plackett-Luce ou Thurstone-Mosteller et un classement prudent, privées par défaut et publiques avec le consentement du
membre.**

Une cote mesure la force d'un membre à un jeu par rapport aux membres qu'il a affrontés : elle monte avec une victoire
contre un camp plus fort et baisse avec une défaite contre un camp plus faible. Chaque jeu a ses propres cotes, dans un
seul système de cotes :

- **Elo**, d'abord pour les duels : un facteur K configurable, fixe ou décroissant à mesure qu'un membre joue et monte ;
  les parties de plusieurs joueurs et les parties par équipes lues par paires de camps ; les nulles.
- **OpenSkill**, l'approximation bayésienne de Weng et Lin, pour un nombre quelconque de joueurs ou d'équipes : une
  habileté et son incertitude, le modèle Plackett-Luce ou le modèle Thurstone-Mosteller, et un classement ordonné par
  l'estimation prudente `mu − 3 × sigma`, de sorte qu'un nouveau venu monte à mesure que l'incertitude diminue.

Une cote est **privée par défaut** : seul le membre la voit jusqu'à ce qu'il la rende publique, et seules les cotes
publiques figurent au classement ; une cote après peu de parties est marquée **provisoire**, et le classement peut
l'omettre. Chaque résultat n'est compté qu'**une fois** : l'enregistrer de nouveau est refusé. Un résultat **amical**
est joué hors des cotes et ne change rien. Les membres sont
désignés par des identifiants stables et opaques, jamais par un nom. Les cotes sont gardées en nombres entiers d'une
virgule fixe, arrondis une fois par jeu : **rejouer** les résultats d'un jeu à partir de leur source donne donc les
mêmes cotes à chaque fois — après une correction, un résultat supprimé ou un changement de système.

Le paquet s'adresse à une application qui tient des soirées de jeu, un classement de club ou des tables en ligne et veut
un niveau pour chaque joueur, montré avec soin. C'est une bibliothèque PHP simple : le dépôt de stockage et la source
des résultats sont ceux de l'application ; un résultat coté envoie des événements que l'application écoute, pour montrer
le changement ou jouer une célébration quand une cote atteint un nouveau sommet.

## État

| Partie | État |
|---|---|
| `Ratings` : l'enregistrement de chaque résultat une fois, les résultats amicaux, les cotes provisoires, le classement avec ou sans elles, la publication et le masquage, la relecture, l'export et l'effacement | Implémenté et testé |
| `GameResult` : les duels, les rangs de plusieurs joueurs, les parties par équipes, les nulles, écrits et lus en valeurs simples | Implémenté et testé |
| `Elo\EloSystem`, `Elo\KFactor`, `Elo\KFactorStep` : Elo avec un K fixe ou décroissant, une échelle et un plancher | Implémenté et testé |
| `OpenSkill\OpenSkillSystem` : Plackett-Luce et Thurstone-Mosteller, l'estimation prudente | Implémenté et testé |
| `MemberRating`, `Visibility`, `RatingChange` : la cote d'un membre telle qu'elle est stockée, privée par défaut | Implémenté et testé |
| `Contract\RatingStore`, `Store\InMemoryRatingStore`, `Contract\PlaySource` | Implémenté et testé |
| `Event\RatingChanged`, `Event\PeakReached` : les moments d'une célébration | Implémenté et testé |
| Exceptions sous une seule interface `RatingException` | Implémenté et testé |
| Une source de résultats qui lit `tilttek/play-log` | Non fournie : `tilttek/play-log` n'est pas encore écrit ; l'application implémente `Contract\PlaySource` |
| Glicko-2 | Non fourni |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Enregistrer des résultats](doc/fr/recording-results.md) | Les résultats, les camps et les rangs, chaque résultat enregistré une fois, les résultats amicaux, les changements et les événements. |
| [Elo](doc/fr/elo.md) | La formule, le facteur K, les parties de plusieurs joueurs et d'équipes, l'échelle et le plancher. |
| [OpenSkill](doc/fr/openskill.md) | Les habiletés et l'incertitude, les deux modèles, les réglages et l'estimation prudente. |
| [Vie privée et classement](doc/fr/privacy-and-standings.md) | Privé par défaut, la publication, le classement, les cotes provisoires, l'export et l'effacement. |
| [Rejouer](doc/fr/replaying.md) | La source des résultats, le déterminisme et le changement de système. |
| [Célébrations](doc/fr/celebrations.md) | Les événements d'un changement de cote et d'une nouvelle cote record, avec le kit PHIL UX. |
| [Stockage](doc/fr/storing.md) | Le contrat du dépôt de stockage, les versions et les résultats concurrents. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `RatingException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\Rating\Elo\{EloSystem, KFactor};
use Tilttek\Rating\{GameResult, Ratings};
use Tilttek\Rating\Store\InMemoryRatingStore;

$ratings = new Ratings(new InMemoryRatingStore(), new EloSystem(KFactor::chess()));

$ratings->record(GameResult::duel('play-81', 'chess', new DateTimeImmutable(), 'member-1042', 'member-1077'));
$ratings->rating('chess', 'member-1042')->value();     // 1520.0, seen by the member alone
$ratings->publish('chess', 'member-1042');             // on the member's own choice
$ratings->standings('chess');                          // the public ratings, highest first
```

## Prérequis

PHP 8.5 ou une version plus récente et `psr/event-dispatcher`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
