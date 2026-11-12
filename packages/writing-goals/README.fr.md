<!-- phil-dev source-sha256=ea24576b8e306366c32f7694e8aee91a6be07e96abdd0896ba950263b228207d -->
# tilttek/writing-goals

**Les objectifs d'écriture d'un auteur : un plan qui s'ajuste aux jours où il écrit vraiment, et aucune série à ne pas rompre.**

tilttek/writing-goals tient les objectifs d'écriture d'un auteur et en suit l'avancement. Un objectif est une cible en
mots ou en pages, d'un premier jour à une échéance, les deux inclus, répartie sur les jours de la semaine où l'auteur
choisit d'écrire, moins les dates qu'il met de côté. Son plan suit une courbe : la même part chaque jour, davantage au
début, davantage à la fin, ou des poids que l'auteur dessine. Le quota de chaque jour d'écriture est ce qui reste,
partagé entre les jours d'écriture restants, en proportion de leur poids dans l'ensemble du plan : un jour sans écriture
n'est pas un échec, sa part passe aux jours suivants, qui gardent la forme de la courbe ; un jour d'avance les abaisse.
L'auteur consigne ce qu'il écrit chaque jour, retire un compte consigné trop haut, ou corrige un jour. Un plan répond ce
qui a été écrit, ce qui reste, le quota du jour et ce qu'il en reste, le quota de chaque jour d'écriture jusqu'à
l'échéance, la part de la cible écrite, et si l'objectif est atteint — l'application en est informée une fois — ou
dépassé son échéance, que l'auteur repousse sans rien perdre. Aucune série n'est comptée. Effacer un auteur supprime ses
objectifs et ses comptes.

Il s'adresse à une plateforme d'écriture ou à une communauté qui veut aider les auteurs à terminer ce qu'ils commencent
— un roman en un mois, un livre-jeu en une saison — sans la pression d'une chaîne qui se rompt au premier jour manqué.

C'est une bibliothèque PHP simple dont les dépendances sont `phil/resource`, la référence opaque d'un auteur,
`psr/clock`, l'horloge où se lit le jour d'aujourd'hui de l'auteur, et `psr/event-dispatcher`, qui informe l'application
d'un objectif atteint. Les auteurs sont des références que donne l'application, `member:44` : la bibliothèque ne lit
jamais un nom, un courriel ni un texte ; l'application compte les mots et consigne le compte.

La conception est petite : un `Goal` tient sa cible, ses `Day`, son `Unit`, sa `Curve` et ses `WritingDays` ; un
`Planner` transforme un objectif et son `Progress` en un `Plan` de `DailyQuota` ; `WritingGoals` garde les objectifs
dans un `GoalStore`, consigne les comptes dans un `LogStore` et envoie `GoalReached`.

## État

| Partie | État |
|---|---|
| `Goal`, `Day`, `Unit` : une cible en mots ou en pages d'un premier jour à une échéance, dans le fuseau horaire de l'auteur | Implémenté et testé |
| `Curve`, `CurveShape` : linéaire, chargée au début, chargée à la fin ou poids personnalisés | Implémenté et testé |
| `WritingDays` : les jours de la semaine où l'auteur écrit, moins les dates mises de côté | Implémenté et testé |
| `Planner`, `Plan`, `DailyQuota` : le quota quotidien recalculé à partir de ce qui reste, en nombres entiers dont la somme est ce reste | Implémenté et testé |
| `Progress` : les comptes de chaque jour | Implémenté et testé |
| `WritingGoals` : objectifs gardés, comptes consignés, retirés et corrigés, plans au jour d'aujourd'hui de l'auteur, effacement | Implémenté et testé |
| `GoalReached` : la cible atteinte, annoncée à l'application | Implémenté et testé |
| `GoalStore`, `LogStore` et leurs implémentations en mémoire, avec les contrats que réussit tout magasin | Implémenté et testé |
| Exceptions sous une seule interface `WritingGoalsException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement de la bibliothèque. |
| [Objectifs et courbes](doc/fr/goals-and-curves.md) | Les cibles, les jours, les fuseaux horaires, les jours d'écriture et les quatre courbes. |
| [Plans et quotas](doc/fr/plans-and-quotas.md) | Comment se calcule le quota quotidien, les jours manqués, les jours d'avance, l'objectif atteint et l'échéance. |
| [Consigner](doc/fr/logging.md) | Consigner, retirer et corriger les comptes, l'objectif atteint, célébrer et effacer. |
| [Écrire un magasin](doc/fr/writing-a-store.md) | Les deux contrats qu'implémente le stockage d'une application. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque : les magasins, l'horloge, le répartiteur. |
| [Exceptions](doc/fr/exceptions.md) | `WritingGoalsException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui consigne des comptes. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/writing-goals
```

```php
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\Clock;
use Tilttek\WritingGoals\{Curve, Day, Goal, WritingDays, WritingGoals};
use Tilttek\WritingGoals\Store\{InMemoryGoalStore, InMemoryLogStore};

$goals = new WritingGoals(new InMemoryGoalStore(), new InMemoryLogStore(), new Clock(), $dispatcher);

$goals->save(new Goal(
    'novel-2026',
    ResourceReference::of('member', '44'),
    50_000,
    Day::fromString('2026-11-01'),
    Day::fromString('2026-11-30'),
    curve: Curve::frontLoaded(),
    writingDays: WritingDays::on(1, 2, 3, 4, 5, 6),
    timezone: 'America/Montreal',
));

$plan = $goals->log('novel-2026', 1_200);
$plan->todayQuota;   // what today asks for
$plan->leftToday();  // what is left of it
$plan->remaining();  // what is left of the target
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
