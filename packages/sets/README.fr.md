<!-- phil-dev source-sha256=438bf56dcdabddec0c3fdfcc3e56f7f53914c013107e39d017939fddcda67fd7 -->
# tilttek/sets

**Des ensembles pour les badges, les collections et autres jeux de ludification : définitions versionnées, paliers,
complétion calculée à partir de ce qu'un détenteur possède, récompenses et événements déclarés, sans rien accorder.**

Un ensemble est une liste de membres à réunir — badges, cartes, dés, autocollants —, chacun nommé par un identifiant
stable et présenté dans l'ordre de l'ensemble. Un détenteur le complète en possédant chaque membre ; les paliers
marquent des étapes avant ou au-delà — *5 sur 20*, *les 20 au niveau 2* —, de sorte qu'un ensemble refait à des
niveaux supérieurs reste un seul ensemble, avec un palier par niveau. La bibliothèque mesure ce qu'un détenteur
possède par rapport à un ensemble — possédés, manquants, pourcentage, paliers atteints —, enregistre chaque complétion
une seule fois, avec la version de l'ensemble sous laquelle elle a été obtenue, et l'annonce par un événement qui
porte les récompenses que l'ensemble ou le palier déclare. Les récompenses sont des références opaques : l'application
les accorde, avec les paquets dont elle dispose.

Un ensemble modifié reçoit une nouvelle version, et une version, une fois définie, n'est jamais réécrite : une
complétion enregistrée sous une version antérieure demeure quand l'ensemble s'agrandit, et la progression le dit, pour
que l'application invite le détenteur à compléter les nouveaux membres.

C'est la brique neutre sous les ensembles de badges, les ensembles de collection et les planches d'autocollants : elle
n'a ni critère, ni rareté, ni inventaire, ni notion de ce qu'est un membre. Les détenteurs sont des références de
ressource opaques de `phil/resource`, jamais une adresse courriel ; les moments sont en UTC. C'est une bibliothèque
PHP simple, avec des ports pour ses stockages et des adaptateurs en mémoire.

## État

| Partie | État |
|---|---|
| `SetDefinition`, `Tier`, `Reward` : ensembles versionnés, membres ordonnés, paliers vérifiés du plus facile au plus difficile, récompenses opaques, lus depuis des tableaux et du JSON et écrits vers eux | Implémenté et testé |
| `Holdings`, `Evaluation` : ce qu'un détenteur possède, à un niveau, mesuré par rapport à un ensemble | Implémenté et testé |
| `Sets`, `Progress`, `Completion` : versions définies, progression, complétions enregistrées une seule fois et jamais reprises | Implémenté et testé |
| `Event\SetCompleted`, `Event\TierReached` : annoncés une seule fois, avec les récompenses déclarées | Implémenté et testé |
| `Contract\DefinitionStore`, `Contract\CompletionStore` et leurs adaptateurs en mémoire | Implémenté et testé |
| Un adaptateur de base de données | Non fourni : l'application implémente les deux stockages |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Définir des ensembles](doc/fr/defining-sets.md) | Membres, paliers, récompenses, versions, et lecture d'une définition depuis la configuration ou du JSON. |
| [Progression et complétions](doc/fr/progress-and-completions.md) | Possessions, évaluation, enregistrement, événements, versions et effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `SetsException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Phil\Resource\ResourceReference;
use Tilttek\Sets\{Holdings, Reward, SetDefinition, Sets, Tier};
use Tilttek\Sets\Store\{InMemoryCompletionStore, InMemoryDefinitionStore};

$sets = new Sets(new InMemoryDefinitionStore(), new InMemoryCompletionStore(), events: $dispatcher);

$sets->define(new SetDefinition('starter-dice', 1, ['d4', 'd6', 'd8', 'd10', 'd12', 'd20'],
    tiers: [new Tier('half', required: 3), new Tier('shiny', level: 2, rewards: [new Reward('badge', 'shiny-dice')])],
    rewards: [new Reward('points', 'xp', 50)],
));

$holder = ResourceReference::of('member', '1042');
$progress = $sets->record('starter-dice', $holder, Holdings::of(['d4', 'd6', 'd20']));
$progress->evaluation->percent();      // 50
$progress->evaluation->missing;        // ['d8', 'd10', 'd12']
$progress->recorded;                   // the tier "half", told by Event\TierReached
```

## Prérequis

PHP 8.5 ou une version plus récente ; `phil/resource` 0.1, `psr/clock` et `psr/event-dispatcher`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
