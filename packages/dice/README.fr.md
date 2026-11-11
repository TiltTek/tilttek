<!-- phil-dev source-sha256=c0c99bc8bc13f5bf83a556e633f88c4d5fa76d129efc0bc756406057d341a6d2 -->
# tilttek/dice

**La notation des dés analysée, vérifiée, normalisée et lancée — et chaque dé gardé pour montrer d'où vient le
résultat.**

tilttek/dice lit une expression de dés comme `4d6kh3`, `d20 adv`, `3d10!!`, `6d10>=7f1`, `8d10c[>=6]-@c[=1]`,
`ceil(1d6/3)`, `(1d8+2)*2, 1d4` ou `8d10; @1c[>=6]; "$2 successes"`. Elle refuse une expression invalide en donnant la
position de l'erreur, la réécrit sous une forme canonique et la lance. Les instructions se réfèrent les unes aux
autres, bifurquent selon un résultat, tirent dans des listes de textes et écrivent des phrases : un résultat est un
nombre, un texte ou une liste. Un lancer garde chaque dé, chaque relance, chaque explosion, ce qui a été gardé ou
écarté, et chaque total. Elle lance aussi des dés personnalisés dont les faces sont des symboles, lit et écrit la
notation d'autres langues (`2W6` en allemand), lit une commande du DiceParser de Rolisteam, résout les macros que garde
une application (`$attack`), écrit une expression dans le dialecte d'une table virtuelle, et fait des lancers que
quiconque peut vérifier ou qui se rejouent dé pour dé. Elle ne dessine pas les dés, ne les anime pas, ne tient pas de
journal et ne sait pas qui lance : une page, un bundle ou une application s'en charge.

Elle s'adresse à tout code PHP qui lance des dés pour des gens : une page de lanceur de dés, un forum de jeu par
messages, une fiche de personnage, une table virtuelle, un livre-jeu. `tilttek/dice-bundle` l'intègre à Symfony ; une
application sans Symfony prend la bibliothèque seule.

C'est une bibliothèque PHP simple, sans autre dépendance que les extensions `ctype`, `mbstring` et `sodium`. Son
hasard est un `Random\Randomizer` que passe l'appelant : un moteur sûr en production, un germe fixe dans les tests, le
germe d'un lancer vérifiable ou rejouable. L'analyseur est un analyseur lexical et un analyseur à descente récursive
écrits à la main, sans expression régulière appliquée à l'entrée.

La conception est petite : un `DiceParser` donne un `Pool` d'arbres d'expressions — un `RolisteamReader` aussi, à
partir de la notation de DiceParser. Un `Roller` lance un lot en un `PoolResult`. Un `NotationWriter` et les classes
`Dialect` réécrivent un lot. `FairRoller` et `Replayer` font des lancers vérifiables et rejouables, et `DiceRoller` les
réunit en un seul objet.

## État

| Partie | État |
|---|---|
| Notation : dés, `d%`, dés Fudge pondérés ou non, dés sur un intervalle, dés personnalisés, nombres et faces lancés d'abord, listes de valeurs, les dés précédents, lots | Implémenté et testé |
| Modificateurs : relances répétées ou uniques, dés uniques, explosions étalées, composées, pénétrantes ou limitées, dés tous identiques, planchers et plafonds, transformations, filtres, garder et écarter, avantage, réussites et échecs, occurrences, groupes, marques critiques, jetons de regard, tri | Implémenté et testé |
| Validateurs : valeurs, intervalles, comparaisons, restes, `&`, `|` et `^`, et leur portée | Implémenté et testé |
| Arithmétique entière avec l'arrondi que donne l'appelant ; `floor`, `ceil`, `round`, `abs`, `min`, `max`, `repeat`, reste et puissance | Implémenté et testé |
| Erreurs avec leur position ; limites sur les dés, les faces, les explosions, les relances, les répétitions, les instructions, la longueur et les textes | Implémenté et testé |
| Forme canonique et notations d'autres langues (allemand, danois, norvégien, suédois intégrés) | Implémenté et testé |
| Lancers qui gardent chaque dé ; dés personnalisés comptés par symbole et additionnés quand leurs faces comptent | Implémenté et testé |
| Lancers vérifiables par engagement du germe ; lancers rejouables | Implémenté et testé |
| Dialectes : Roll20, Foundry VTT, Avrae et le DiceParser de Rolisteam, avec signalement des constructions que chacun ne sait pas écrire | Implémenté et testé |
| Instructions, références, dés des instructions liés ou fusionnés, groupes, listes de textes, phrases, `if` et `switch`, `repeat()`, commentaires | Implémenté et testé |
| Le lecteur de la notation du DiceParser de Rolisteam, éprouvé par fuzzing | Implémenté et testé |
| Macros nommées par jeton, `$name`, résolues par l'application | Implémenté et testé |
| Exceptions sous une seule interface `DiceException` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Notation](doc/fr/notation.md) | Chaque construction, les validateurs et leur portée, l'ordre des modificateurs, la forme canonique et les erreurs. |
| [Instructions et résultats](doc/fr/instructions.md) | Les instructions et les références, les groupes, les listes de textes, les textes et les phrases, les branches, les répétitions, les commentaires, et les résultats qui ne sont pas des nombres. |
| [Lancer](doc/fr/rolling.md) | `Roller`, le hasard, les résultats et ce qu'ils gardent, les opérateurs répétés, l'arithmétique et son arrondi. |
| [Dés personnalisés](doc/fr/custom-dice.md) | Les faces, les libellés, les formes et les couleurs, la règle d'accessibilité, les décomptes. |
| [Langues](doc/fr/languages.md) | `Notation` et `Notations` : les lettres d'une langue, lues et écrites. |
| [Dialectes](doc/fr/dialects.md) | Écrire un lot pour Roll20, Foundry VTT, Avrae et le DiceParser de Rolisteam, et ajouter un dialecte. |
| [Le DiceParser de Rolisteam](doc/fr/rolisteam.md) | Lire une commande de DiceParser dans l'arbre de la bibliothèque, ce qui est lu et ce qui est refusé. |
| [Macros](doc/fr/macros.md) | `$name` lu comme un jeton, le contrat `Macros`, ses règles et ses erreurs. |
| [Lancers vérifiables et rejouables](doc/fr/verifiable-and-replayable.md) | Les germes, les engagements, les germes du client et les *nonces* ; les identifiants de relecture. |
| [Limites](doc/fr/limits.md) | Les bornes contre les abus et l'endroit où elles sont vérifiées. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application fournit à la bibliothèque : macros, dialectes, notations, dés personnalisés, limites, hasard. |
| [Exceptions](doc/fr/exceptions.md) | `DiceException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui lance des dés. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/dice
```

```php
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;

$dice = new DiceRoller();
$pool = $dice->parse('4D6KH3, d20 adv');
$randomizer = new Randomizer(new Secure());

$pool->canonical();                                   // "4d6kh3, 2d20kh1"
$result = $dice->roll($pool, $randomizer);
$result->totals();                                    // [14, 17], for instance
$dice->translate($pool, 'roll20')->text();            // "/r 4d6kh3\n/r 2d20kh1"
$dice->write($dice->parse('2W6+1', 'de'));            // "2d6+1"
$dice->roll($dice->parse('1d6/3'), $randomizer, RoundingMode::PositiveInfinity)->totals(); // [2], rounded up
$dice->roll($dice->parse('1d20i[>=15]{"hit"}{"miss"}'), $randomizer)->outputs();         // ["miss"], for instance
```

## Prérequis

PHP 8.5 ou plus récent, avec les extensions `ctype`, `mbstring` et `sodium`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
