<!-- phil-dev source-sha256=a343a62c544978599c70f2086ed1c0c1b99222ee3854c581a1f261522a801c78 -->
# tilttek/quests

**Des quêtes et des défis par étapes, lus dans ce que font les membres : pour un membre, une équipe, toute la communauté, ou deux membres qui s'affrontent.**

tilttek/quests transforme l'activité des membres, enregistrée par `tilttek/activity`, en quêtes accomplies. Une quête
est une liste d'étapes (*jouer trois parties*, *lire 300 pages d'un livre de règles*, *publier une première fiche*)
faites dans l'ordre ou dans n'importe quel ordre. Elle est ouverte en tout temps, entre deux dates dans un fuseau
horaire déclaré, ou à des dates qui reviennent, et elle est accomplie une fois par occurrence. Les quêtes s'enchaînent :
en accomplir une ouvre la suivante. Un membre suit une quête dès qu'elle est ouverte ou une fois qu'il l'accepte, et
peut l'abandonner. En plus de la quête personnelle, trois genres sont intégrés. Le défi de la communauté est un
objectif collectif que fait avancer l'activité de tout le monde (*mille parties ce mois-ci*). La quête d'équipe se
rejoint sous une équipe, vers un objectif que partagent ses membres. Le duel est un défi qu'un membre lance à un autre,
gagné par le premier qui l'accomplit. Les récompenses sont déclarées, jamais exécutées : une quête accomplie indique à
l'application ses récompenses (des points, un badge, un objet) et les membres à qui elles vont, et l'application les
donne par les paquets qu'elle a. Tout est rejoué à partir du journal quand une règle change, et effacer un membre
retire ce que les quêtes ont gardé de lui.

Elle s'adresse à une application qui donne des objectifs à atteindre à ses membres : un site communautaire, un site de
jeux, une plateforme de lecture ou d'écriture, un club. `tilttek/quests-bundle` l'intègre à Symfony, garde les quêtes
et leur progression dans Doctrine DBAL et calcule les dates saisonnières avec `phil/almanac`. Une application sans
Symfony prend la bibliothèque seule et écrit ses propres magasins, ou utilise ceux en mémoire.

C'est une bibliothèque PHP simple dont les dépendances sont `tilttek/activity`, le journal que lisent ses étapes,
`phil/resource`, la référence opaque d'un membre ou d'une équipe, et `psr/clock`. Les membres sont des références que
donne l'application, `member:44` : la bibliothèque ne lit jamais un nom, un courriel ou un compte. Elle ne dépend
d'aucun autre paquet de ludification : un site qui ne veut que des quêtes n'installe rien d'autre.

La conception est petite : une `Quest` contient ses `Step`, son `Schedule`, ses `Reward` et la quête qu'elle suit ; un
`Catalog` contient les quêtes. `QuestProjection` lit le journal, écrit les `Contribution` et les `Completion`, et
départage les duels. `Quests` accepte, abandonne et lit où en est chaque quête. `Duels` lance les défis et y répond.
`Evaluator` et `ProgressCalculator` lisent la progression d'un sujet à partir de ses contributions.

## État

| Partie | État |
|---|---|
| `Scope`, `Step`, `StepMeasure`, `Reward`, `Quest`, `QuestKind`, `Admission` : étapes comptées ou totalisées, ordonnées ou libres, récompenses déclarées, genres et admission | Implémenté et testé |
| `Schedule`, `Permanent`, `Dated`, `Occurrence`, `ScheduleReader`, `Window` : quêtes ouvertes en tout temps, entre deux dates, ou à des dates que calcule un lecteur propre à l'application | Implémenté et testé |
| `QuestReader`, `Catalog`, `InMemoryCatalog`, `WritableCatalog`, `Chains` : quêtes à partir de valeurs simples, leurs enchaînements vérifiés | Implémenté et testé |
| `Contribution`, `Enrolment`, `Completion`, et leurs quatre magasins avec les implémentations en mémoire et les contrats que tout magasin respecte | Implémenté et testé |
| `ProgressCalculator`, `QuestProgress`, `StepProgress`, `Finish`, `Evaluator` : la progression d'un sujet sur une occurrence | Implémenté et testé |
| `QuestProjection` : l'activité transformée en progression, quêtes accomplies, enchaînements ouverts, duels départagés, annulations et reconstructions | Implémenté et testé |
| `Quests` : acceptation, abandon, état, tableau, journal des quêtes accomplies, exportation et effacement | Implémenté et testé |
| `Duel`, `DuelStatus`, `Duels`, `DuelStanding` : défis lancés, acceptés, refusés, retirés, gagnés et expirés | Implémenté et testé |
| `QuestCompleted`, `QuestObserver` : ce qui est annoncé, avec les récompenses et leurs membres | Implémenté et testé |
| Exceptions sous une seule interface `QuestsException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Quêtes et étapes](doc/fr/quests-and-steps.md) | Étapes, étapes ordonnées, récompenses, la définition que contient une configuration. |
| [Calendriers et enchaînements](doc/fr/schedules-and-chains.md) | Quand une quête est ouverte, ses occurrences, et les quêtes qui en suivent d'autres. |
| [Suivre une quête](doc/fr/following.md) | Automatique ou acceptée, abandon, où en est une quête pour un membre. |
| [Genres](doc/fr/genres.md) | Quêtes personnelles, défis de la communauté, quêtes d'équipe et duels. |
| [La projection](doc/fr/projection.md) | Comment le journal est lu, ce qui est annoncé, les annulations et les reconstructions. |
| [Effacement](doc/fr/erasure.md) | Ce que couvrent l'exportation et l'effacement d'un membre. |
| [Écrire un magasin](doc/fr/writing-a-store.md) | Les quatre contrats de magasin que le stockage d'une application implémente. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque : le catalogue, les magasins, les calendriers, l'observateur, l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `QuestsException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et sa barre de mutation, et comment tester du code qui lit les quêtes. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/quests
```

```php
use Tilttek\Quests\InMemoryCatalog;
use Tilttek\Quests\QuestReader;

$catalog = InMemoryCatalog::fromArray([
    'first-steps' => [
        'ordered' => true,
        'steps' => [['code' => 'play', 'verbs' => ['game.played'], 'count' => 3], ['code' => 'write', 'verbs' => ['sheet.published']]],
        'rewards' => [['kind' => 'points', 'code' => 'xp', 'amount' => 50]],
    ],
    'thousand-games' => ['kind' => 'community', 'opens' => '2026-12-01', 'closes' => '2027-01-01', 'steps' => [['verbs' => ['game.played'], 'count' => 1000]]],
], new QuestReader('America/Toronto'));

// Wire QuestProjection to the journal, then read:
$quests->status($member, 'first-steps')->progress->done(); // 1
```

## Prérequis

PHP 8.5 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
