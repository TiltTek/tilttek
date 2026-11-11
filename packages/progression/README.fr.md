<!-- phil-dev source-sha256=2b6ba169d81033a851c7d0ed457053418d3046d3799a02c01403a8e5442a0e2b -->
# tilttek/progression

**De l'expérience qui s'accumule en niveaux, des saisons qui font recommencer une piste, et des séries comptées dans la
journée propre à chaque membre.**

tilttek/progression transforme l'activité des membres, enregistrée par `tilttek/activity`, en expérience et en séries.
L'expérience est gagnée selon des règles (*cinquante par partie menée comme meneur de jeu, deux cents au plus par
jour*) dans un compte qui n'est jamais dépensé. Une courbe dit à partir de quelle expérience commence chaque niveau :
linéaire, quadratique, exponentielle, ou une table qu'écrit l'application. Un site garde autant de pistes qu'il lui en
faut (joueur, meneur de jeu, auteur), chacune allant au membre ou à l'objet du geste, comme un personnage dont
l'expérience suit les règles de son jeu. Une piste peut recommencer à chaque saison (chaque mois, trimestre ou année,
ou des périodes nommées comme un événement d'été) et garde ce qui a été atteint dans les saisons passées. Les jalons
d'expérience ou de niveau sont annoncés une seule fois, pour que l'application les récompense. Une série compte les
jours ou les semaines consécutifs où un membre fait un geste, lus dans le fuseau horaire du membre : minuit à Montréal
ne brise pas une série à Paris. Une grâce pardonne quelques jours manqués, un jour gelé garde la série en vie, et la
meilleure série est gardée à côté de la série en cours. Tout est rejoué à partir du journal quand une règle change, et
effacer un membre retire ce qu'il a gagné.

Elle s'adresse à une application qui récompense ce que font ses membres et qui veut leur montrer le chemin parcouru :
un site communautaire, un site de jeux, une plateforme de lecture ou d'écriture. `tilttek/progression-bundle`
l'intègre à Symfony et garde la progression dans Doctrine DBAL. Une application sans Symfony prend la bibliothèque
seule et écrit ses propres magasins, ou utilise ceux en mémoire.

C'est une bibliothèque PHP simple dont les dépendances sont `tilttek/activity`, le journal que lisent ses règles et ses
séries, `phil/resource`, la référence opaque d'un membre ou d'un objet, et `psr/clock`. Les membres sont des références
que donne l'application, `member:44` : la bibliothèque ne lit jamais un nom, un courriel ou un compte.

La conception est petite : un `Track` contient une `Curve`, ses `ExperienceRule`, son `SeasonSchedule` et ses
`Milestone`. `ProgressionProjection` lit le journal et écrit les `Gain`, les jalons et les gestes de série dans trois
magasins. `Progression` lit les positions, les saisons passées, les jalons et les séries, gèle des jours, balaie les
séries brisées et efface un membre. `StreakKeeper` et `StreakCalculator` comptent les séries.

## État

| Partie | État |
|---|---|
| `Curve` et `Level` : courbes linéaires, quadratiques, exponentielles et en table, où en est l'expérience | Implémenté et testé |
| `ExperienceRule`, `Cap`, `Period`, `Gain` : expérience par geste, plafonds par sujet et par période | Implémenté et testé |
| `Track`, `Tracks`, `Subject`, `Milestone` : pistes de membres ou d'objets, à partir d'une configuration | Implémenté et testé |
| `Season`, `CalendarSeasons`, `NamedSeasons` : saisons par mois, trimestre ou année, ou nommées | Implémenté et testé |
| `StreakRule`, `StreakRules`, `StreakUnit`, `Grace`, `StreakCalculator`, `StreakKeeper` : séries de jours ou de semaines, grâce, unités gelées, le balayage | Implémenté et testé |
| `TimezoneResolver`, `FixedTimezone`, `ContextTimezone` : le fuseau horaire du membre | Implémenté et testé |
| `ProgressionProjection` : l'activité transformée en expérience et en séries, changements de niveau, jalons, annulations et reconstructions | Implémenté et testé |
| `Progression` : positions, saisons passées, jalons, séries, gels, balayage, effacement | Implémenté et testé |
| Trois magasins et leurs implémentations en mémoire, avec les contrats que tout magasin respecte | Implémenté et testé |
| Exceptions sous une seule interface `ProgressionException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Pistes et niveaux](doc/fr/tracks-and-levels.md) | Courbes, règles, plafonds, sujets, positions et la configuration des pistes. |
| [Saisons](doc/fr/seasons.md) | Saisons du calendrier et saisons nommées, et les saisons passées. |
| [Jalons](doc/fr/milestones.md) | Des étapes d'expérience ou de niveau qui méritent une récompense, annoncées une fois. |
| [Séries](doc/fr/streaks.md) | Jours ou semaines consécutifs, le fuseau horaire du membre, la grâce, les jours gelés et le balayage. |
| [La projection](doc/fr/projection.md) | Comment le journal est lu, ce qui est annoncé, les annulations et les reconstructions. |
| [Effacement](doc/fr/erasure.md) | Ce qu'effacer un membre retire. |
| [Écrire un magasin](doc/fr/writing-a-store.md) | Les trois contrats de magasin que le stockage d'une application implémente. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque : magasins, saisons, fuseaux horaires, l'observateur, l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `ProgressionException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et sa barre de mutation, et comment tester du code qui lit la progression. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/progression
```

```php
use Tilttek\Progression\Streak\StreakRules;
use Tilttek\Progression\Tracks;

$tracks = Tracks::fromArray([
    'player' => [
        'curve' => ['type' => 'quadratic', 'base' => 50, 'levels' => 60],
        'rules' => ['games' => ['verbs' => ['game.played'], 'experience' => 20, 'cap' => ['amount' => 100, 'period' => 'day']]],
        'milestones' => ['veteran' => ['level' => 10]],
    ],
], 'America/Toronto');
$streaks = StreakRules::fromArray(['daily-play' => ['verbs' => ['game.played'], 'grace' => ['allowed' => 1, 'window' => 7]]]);

// Wire ProgressionProjection to the journal, then read:
$progression->standing($member, 'player')->level->number; // 3
$progression->streak($member, 'daily-play')->current;     // 5
```

## Prérequis

PHP 8.5 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
