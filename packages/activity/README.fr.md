<!-- phil-dev source-sha256=7402a0a9fb1bac0e0d891bc8dc9484f081cebc29df7c7091282a5592d447ec92 -->
# tilttek/activity

**Ce que vos membres ont fait, enregistré une seule fois, pour que les points, les badges et les quêtes le comptent tous
de la même façon.**

tilttek/activity tient un journal des gestes des membres : *ce membre a fait ce geste, sur cet objet, à ce moment*.
L'application enregistre chaque geste avec une clé d'idempotence. Un écouteur qui s'exécute deux fois ou une tâche
relancée ne le compte donc jamais deux fois. Elle annule un geste défait — une commande remboursée, un commentaire
retiré — par une entrée distincte, jamais en réécrivant le journal. Les lecteurs comptent le contenu du journal par
membre, verbe, objet et période. Les projections y tiennent leurs propres totaux à jour, puis le rejouent quand une
règle change. Effacer un membre supprime ses gestes et l'oublie comme objet des gestes des autres. La bibliothèque
n'attribue ni points, ni badges, ni niveaux, et ne conserve jamais un texte écrit par le membre : les paquets qui
récompensent les gestes lisent ce journal, et un commentaire est référencé, pas copié.

Elle s'adresse à une application qui récompense ce que font ses membres — un site communautaire, le programme de
fidélité d'une boutique, un site de jeux — et qui veut que chaque récompense vienne d'un seul registre, relisible.
`tilttek/activity-bundle` l'intègre à Symfony et garde le journal dans Doctrine DBAL. Une application sans Symfony
prend la bibliothèque seule et écrit son propre `ActivityLog`, ou utilise celui en mémoire.

C'est une bibliothèque PHP simple. Ses seules dépendances sont `phil/resource`, la référence opaque d'un membre ou d'un
objet, et `psr/clock`, l'horloge qui date chaque entrée. Les membres sont des références que donne l'application,
`member:44` : la bibliothèque ne lit jamais un nom, un courriel ou un compte.

La conception est petite : une `Activity` est un fait. Un `Journal` enregistre, annule, lit et compte des activités par
un `ActivityLog`. Un `Filter` choisit des activités. Une `Projection` tient un état dérivé, à laquelle un `Projector`
remet les entrées qu'elle n'a pas vues, avec un point de reprise gardé dans `Checkpoints`.

## État

| Partie | État |
|---|---|
| `Activity`, `Verb`, `VerbPattern`, `IdempotencyKey`, `Context` : le fait et ses parties validées | Implémenté et testé |
| `Journal` : enregistrement unique par clé, annulation unique, verbes déclarés, observateurs avertis des nouvelles entrées | Implémenté et testé |
| `ActivityLog` et `InMemoryActivityLog` : ajout, annulation, lecture par un filtre, totaux, effacement | Implémenté et testé |
| `Filter` : membre, verbes par motif, objet, période | Implémenté et testé |
| Projections : `Projector` avec points de reprise et lots, reconstruction d'une période, `LiveProjections`, `Projections` | Implémenté et testé |
| Exceptions sous une seule interface `ActivityException` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Enregistrer l'activité](doc/fr/recording.md) | Les activités, les verbes, les clés, le contexte, les annulations, les verbes déclarés et les observateurs. |
| [Lire et compter](doc/fr/reading.md) | Les filtres, les positions, les lectures dans l'ordre et les totaux. |
| [Projections](doc/fr/projections.md) | L'état dérivé tenu à jour, les points de reprise, les lots, les reconstructions et les projections en direct. |
| [Effacement](doc/fr/erasure.md) | Ce qu'efface la suppression d'un membre et ce qu'elle garde. |
| [Écrire un journal](doc/fr/writing-a-journal.md) | Le contrat `ActivityLog` qu'implémente le stockage d'une application. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application fournit à la bibliothèque : le journal, les projections, les points de reprise, les observateurs, l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `ActivityException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui enregistre de l'activité. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/activity
```

```php
use Symfony\Component\Clock\NativeClock;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Phil\Resource\ResourceReference;

$journal = new Journal(new InMemoryActivityLog(), new NativeClock());

$journal->record(Activity::of('game:812:player:44', 'member:44', 'game.played', $playedAt, 'game:812'));
$journal->record(Activity::of('game:812:player:44', 'member:44', 'game.played', $playedAt, 'game:812')); // counted once

$journal->totals(Filter::all()->member(ResourceReference::of('member', '44'))->verbs('game.*'))->count; // 1
$journal->cancel('game:812:player:44');
```

## Prérequis

PHP 8.5 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
