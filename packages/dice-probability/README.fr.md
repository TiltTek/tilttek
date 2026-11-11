<!-- phil-dev source-sha256=a716027b7fe02c4a68b81f2843c34fe3fc0a5e598bf84a73c55a2866ff210aed -->
# tilttek/dice-probability

**Les probabilités exactes d'une expression de dés : chaque total sous forme de fraction, et la chance d'un double.**

tilttek/dice-probability calcule ce qu'une expression de `tilttek/dice` peut donner et la probabilité de chaque valeur :
`4d6kh3`, `2d20kh1`, `6d10>=7f1`, `(1d4)d6`, `1d6!`. Chaque probabilité est une fraction exacte sur le nombre de façons
de lancer les dés (`6/36` pour un 7 sur `2d6`). Elle est accompagnée de la probabilité d'obtenir au moins et au plus
chaque valeur, de la moyenne et de l'écart type. La bibliothèque donne aussi la probabilité d'événements sur les faces
d'un ensemble de dés : au moins un 1, exactement deux six, un double, une suite, toutes les faces différentes, et leurs
combinaisons. Elle compare aussi deux expressions, comme une attaque contre une défense. Les explosions sont suivies
jusqu'à une profondeur, et la probabilité d'aller plus loin est indiquée. Au-delà d'une taille, ou pour les quelques
constructions qu'elle ne suit pas, une estimation avec germe prend le relais. Elle est marquée comme estimée et donne sa
marge.

Elle s'adresse à tout code PHP qui explique les dés à des personnes : une page de probabilités, un concepteur de jeux
qui compare deux mécaniques, une fiche de personnage qui montre les chances d'un jet. `tilttek/dice-probability-bundle`
l'intègre à Symfony, avec un tableau, un graphique et un Live Component.

C'est une bibliothèque PHP simple qui dépend seulement de `tilttek/dice`. Elle gère elle-même ses grands nombres, sans
`bcmath` ni `gmp`. Les sommes de dés sont des convolutions : un dé simple est additionné par une fenêtre glissante. Les
sélections (garder et retirer des dés) parcourent les multiensembles triés des dés, jamais chaque jet.

## État

| Partie | État |
|---|---|
| Distributions exactes : arithmétique, fonctions, dés, relances, explosions, planchers, transformations, filtres, garder et retirer, succès et échecs, jugements de l'ensemble, nombres de dés et de faces lancés d'abord | Implémenté et testé |
| Au moins, au plus, moyenne, variance, écart type, probabilité d'un validateur | Implémenté et testé |
| Événements : nombre d'une face ou d'un validateur, dés identiques, suites, toutes égales, toutes différentes, toutes conformes, composés | Implémenté et testé |
| Comparaison de deux expressions | Implémenté et testé |
| Explosions jusqu'à une profondeur avec ce qui reste ; estimations avec germe et leur marge | Implémenté et testé |
| Nombres exacts de toute taille | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation et une première distribution. |
| [Distributions](doc/fr/distributions.md) | `Probabilities::distribution()`, `Distribution` et ce qui est calculé exactement. |
| [Événements](doc/fr/events.md) | `chance()`, les événements, l'exemple de référence et `Faces`. |
| [Comparer deux expressions](doc/fr/comparing.md) | `compare()` et `Versus`. |
| [Explosions et estimations](doc/fr/explosions-and-estimates.md) | `Settings`, la profondeur des explosions, les estimations et leur marge. |
| [Nombres exacts](doc/fr/exact-numbers.md) | `Natural` et `Fraction`. |
| [Exceptions](doc/fr/exceptions.md) | `ProbabilityException`, `CannotCompute` et les arguments invalides. |
| [Points d'extension](doc/fr/extension-points.md) | Les événements et les dés de l'application. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et comment tester le code qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/dice-probability
```

```php
use Tilttek\Dice\DiceRoller;
use Tilttek\DiceProbability\Event\AnyOf;
use Tilttek\DiceProbability\Event\Count;
use Tilttek\DiceProbability\Event\OfAKind;
use Tilttek\DiceProbability\Probabilities;

$dice = new DiceRoller();
$probabilities = new Probabilities();

$distribution = $probabilities->distribution($dice->parse('2d6'));
(string) $distribution->probability(7);                // "6/36"
(string) $distribution->atLeast(10)->reduced();         // "1/6"

$oneOrDouble = new AnyOf(Count::atLeastOne(1), OfAKind::double());
(string) $probabilities->chance($oneOrDouble, $dice->parse('3d6'))->probability;   // "156/216"

(string) $probabilities->compare($dice->parse('1d20+5'), $dice->parse('2d10+4'))->first->reduced(); // "1/2"
```

## Prérequis

PHP 8.5 ou plus récent, et `tilttek/dice`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
