<!-- phil-dev source-sha256=2fb85c050eeaca7c096772780f769769430c6b7cffddf54df60023ec94ff7ff6 -->
# tilttek/table-safety

**Les outils de sécurité d'une table de jeu de rôle : ce que la table convient avant la partie, les signaux qui arrêtent ou changent une scène pendant la partie, et ce qu'elle a aimé après — anonymes par défaut.**

tilttek/table-safety garde les réponses et les signaux des outils de sécurité qu'utilisent les communautés du jeu de
rôle sur table et du jeu de rôle grandeur nature, autour d'une table ou en ligne ; l'application en dessine les écrans.
Avant la partie, chaque participant donne ses lignes et ses voiles et sa liste de consentement — vert, jaune ou rouge —
sur les thèmes de `phil/content-warnings`, la même liste qui avertit du contenu d'un jeu, plus les thèmes que la table
ajoute ; chacun répond à la séance zéro que son meneur de jeu a composée à partir d'un gabarit intégré. Pendant la
partie, chacun lève la carte X, une pause, un retour en arrière ou une avance rapide de Script Change, ou sort par la
porte ouverte, sans expliquer pourquoi ; le meneur de jeu demande un point d'étape, et chacun répond par un geste. Après
la partie, chacun donne ses étoiles et ses souhaits pour la séance.

Les sommaires ne disent jamais qui a donné quelle réponse : une table voit, pour chaque thème, la réponse la plus
restrictive que quiconque a donnée, les décomptes de sa séance zéro, ses étoiles et ses souhaits en ordre alphabétique.
Un signal est anonyme à moins que la table n'ait choisi des signaux nominatifs, et rien — stocké ou diffusé — ne garde
l'auteur d'un signal anonyme. Les réponses sont sensibles : elles ne sont gardées que pour leur table, et celles d'un
participant sont exportées et effacées avec son compte.

C'est une bibliothèque PHP simple, pour toute application qui fait tourner des tables : un club, un congrès, un site de
jeu par message, une table virtuelle. Dans une application Symfony, prenez `tilttek/table-safety-bundle`, qui ajoute le
stockage Doctrine, les formulaires, les écrans de la table et du meneur de jeu, et les signaux en temps réel par
Mercure.

Les outils viennent de leurs communautés ; la bibliothèque les décrit dans ses propres mots et en crédite les auteurs
dans [Crédits](doc/fr/credits.md).

## État

| Partie | État |
|---|---|
| `LinesAndVeils`, `ConsentChecklist` et les thèmes d'une table, sur `phil/content-warnings` | Implémenté et testé |
| `TableAgreement` : la réponse la plus stricte de chaque thème, et les préférences d'avertissement qu'elle représente | Implémenté et testé |
| Séance zéro : `Question`, `Template`, les quatre `Templates` intégrés, `Answers` et le `Pact` | Implémenté et testé |
| Signaux : la carte X, Script Change, la porte ouverte et les points d'étape, anonymes par défaut, vus par la table ou par le meneur de jeu seul | Implémenté et testé |
| `StarsAndWishes` et le `FeedbackSummary` d'une séance | Implémenté et testé |
| `TableSettings`, les quatre contrats de stockage et leurs stockages en mémoire | Implémenté et testé |
| `TableSafety` : le service de tout ce qui précède, et l'export et l'effacement des données d'un participant | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Avant la partie](doc/fr/before-play.md) | Lignes et voiles, liste de consentement, thèmes de la table et ce que la table a convenu. |
| [Séance zéro](doc/fr/session-zero.md) | Questions, gabarits intégrés, questions propres au meneur de jeu, réponses et pacte. |
| [Pendant la partie](doc/fr/during-play.md) | Les signaux, qui les voit, signaux nominatifs et points d'étape. |
| [Après la partie](doc/fr/after-play.md) | Étoiles et souhaits, et le sommaire d'une séance. |
| [Réglages](doc/fr/settings.md) | Ce qu'une table choisit, et ses valeurs par défaut. |
| [Vie privée](doc/fr/privacy.md) | Ce qui reste anonyme, ce qui est gardé, et l'export et l'effacement des données d'un participant. |
| [Écrire un stockage](doc/fr/writing-a-store.md) | Les quatre contrats et ce que chaque implémentation doit faire. |
| [Crédits](doc/fr/credits.md) | Les outils, les personnes et les communautés dont ils viennent, et leurs licences. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application remplace ou ajoute. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/table-safety
```

```php
use Phil\ContentWarnings\BaseCatalogue;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\LinesAndVeils;
use Tilttek\TableSafety\Signal\SignalKind;
use Tilttek\TableSafety\Store\InMemoryFeedbackStore;
use Tilttek\TableSafety\Store\InMemoryParticipationStore;
use Tilttek\TableSafety\Store\InMemorySignalStore;
use Tilttek\TableSafety\Store\InMemoryTableStore;
use Tilttek\TableSafety\TableSafety;

$safety = new TableSafety(BaseCatalogue::load(), new InMemoryTableStore(), new InMemoryParticipationStore(), new InMemorySignalStore(), new InMemoryFeedbackStore());
$table = ResourceReference::of('table', '7');
$now = new DateTimeImmutable();

$safety->answerLinesAndVeils($table, ResourceReference::of('member', '12'), LinesAndVeils::none()->with('spiders', Boundary::Line), $now);
$safety->agreement($table)->lines();           // ['spiders'], without saying whose line it is

$signal = $safety->raise($table, ResourceReference::of('member', '12'), SignalKind::XCard, false, $now);
$signal->isAnonymous();                          // true
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring` et `ext-sodium`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
