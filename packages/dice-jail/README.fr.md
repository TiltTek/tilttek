<!-- phil-dev source-sha256=552f8585560a0c45e8f56649486fdcba7815cd469c811f4c78ae372c61090bd0 -->
# tilttek/dice-jail

**La prison des dés malchanceux et les statistiques qui la fondent : un test d'adéquation honnête, expliqué simplement.**

Un dé qui n'arrête pas de faire des un va en prison : un joueur l'y envoie pour une soirée ou jusqu'à ce qu'il le
libère, et la prison garde l'historique de chaque dé. C'est un geste de jeu : emprisonner un dé ne demande aucune preuve
et ne change rien à la façon dont il roule.

Les statistiques sont ce qu'un joueur montre à la table. `GoodnessOfFit` exécute le test du khi carré de Pearson sur les
lancers d'un dé, contre les probabilités exactes d'un dé équitable — un dé uniforme, ou toute distribution que
`tilttek/dice-probability` calcule —, refuse de juger avant que chaque face soit attendue cinq fois, et donne une
valeur p et un verdict : « pas assez de lancers », « rien d'inhabituel », « plutôt inhabituel » ou « très
inhabituel ». `Explanation` le dit en français ou en anglais simples, avec le lancer moyen à côté de celui qui était
attendu. Aucune phrase ne dit jamais qu'un dé est pipé : le verdict le plus inhabituel demande d'examiner le dé, ou de
refaire des lancers, et les lancers des dés numériques sont dits venir d'un générateur qui sert tous les dés de la même
façon.

Le paquet s'adresse à une table qui joue en ligne ou note ses lancers physiques : l'écran d'un meneur de jeu, une tour
à dés, une feuille de personnage. C'est une bibliothèque PHP simple, bâtie sur `tilttek/dice` et
`tilttek/dice-probability` ; l'application garde l'historique et les lancers au moyen de deux ports.

## État

| Partie | État |
|---|---|
| `Jail\Warden` : emprisonner pour une peine ou jusqu'à la libération, libérer, les dés en prison, l'historique | Implémenté et testé |
| `Jail\Inmate`, `Jail\JailEvent`, `Jail\EventKind`, `Jail\Evidence` : la peine, l'historique et les statistiques montrées | Implémenté et testé |
| `Statistics\GoodnessOfFit`, `Statistics\FitResult`, `Statistics\FaceCount`, `Statistics\Verdict` : le test du khi carré | Implémenté et testé |
| `Statistics\Tally`, `Statistics\ExpectedFaces` : les lancers comptés, le dé équitable à partir d'un intervalle ou d'une distribution | Implémenté et testé |
| `Statistics\Explanation`, `Statistics\Language`, `Statistics\Origin` : le verdict en français et en anglais simples | Implémenté et testé |
| `Statistics\DieAssessor`, `Contract\RollSource`, `Store\InMemoryRollSource` : le test à partir des lancers enregistrés | Implémenté et testé |
| `Contract\JailStore`, `Store\InMemoryJailStore` | Implémenté et testé |
| Une source de lancers qui lit le journal des lancers de `tilttek/dice-bundle` | Pas commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [La prison](doc/fr/jail.md) | Emprisonner, les peines, les libérations, l'historique, et ce que la prison n'est pas. |
| [Les statistiques](doc/fr/statistics.md) | Le test du khi carré, le minimum de lancers, les verdicts et la façon de les lire honnêtement. |
| [Les explications](doc/fr/explanations.md) | Les phrases en français et en anglais, et ce qu'elles ne disent jamais. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `DiceJailException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\DiceJail\Jail\Evidence;
use Tilttek\DiceJail\Jail\Warden;
use Tilttek\DiceJail\Statistics\ExpectedFaces;
use Tilttek\DiceJail\Statistics\Explanation;
use Tilttek\DiceJail\Statistics\GoodnessOfFit;
use Tilttek\DiceJail\Statistics\Language;
use Tilttek\DiceJail\Statistics\Tally;
use Tilttek\DiceJail\Store\InMemoryJailStore;

$result = new GoodnessOfFit()->test(Tally::of($rollsOfTheBlueD20), ExpectedFaces::uniform(20));
$result->verdict;                                       // Verdict::NothingUnusual, most of the time
new Explanation()->explain($result, Language::French);  // « Rien d’inhabituel sur 120 lancers : … »

$warden = new Warden(new InMemoryJailStore(), new NativeClock());
$warden->jail('member-7', 'd20:blue', 'Three natural ones in a row.', evidence: Evidence::of($result));
$warden->release('member-7', 'd20:blue', 'It has served its time.');
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `mbstring` ; `tilttek/dice` 0.2 ou 0.3 et `tilttek/dice-probability` 0.1 ; une
implémentation de `psr/clock`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
