<!-- phil-dev source-sha256=1494108dc32a5c5ed6f4127ecce979576aa86eb843ccefc89d14433dd0562278 -->
# tilttek/badges

**Des badges que vos membres gagnent par ce qu'ils font et qu'ils montrent quand ils le choisissent.**

tilttek/badges attribue des badges et des succès à partir de l'activité des membres, enregistrée par
`tilttek/activity`. Un badge a un critère lu dans le journal — un nombre d'activités, un total de leurs quantités, une
première fois, des jours consécutifs, une période, une combinaison de critères, une valeur que mesure l'application, ou
une attribution à la main. La progression vers lui est calculée et affichée, *7 sur 10*. Les paliers relient les badges
d'une série — bronze, argent, or. Un badge répétable se gagne de nouveau chaque fois que sa cible est de nouveau
atteinte. Un badge révocable est retiré quand l'activité qui l'a valu est annulée. Un succès caché n'est affiché, et
son critère avec lui, qu'une fois gagné. Quand une règle change, les badges sont recalculés à partir du journal sur la
période que choisit l'application.

Un badge gagné reste privé tant que le membre ne le montre pas. Le membre choisit un titre porté à côté de son nom, la
vitrine de son profil et son ordre, ce qu'il partage et où, et quelles autres applications peuvent lire ses badges, un
consentement qu'il retire en tout temps. Effacer un membre supprime ses attributions, les contributions de son
activité, son profil et ses consentements. `tilttek/badges-bundle` délivre un badge comme une attestation Open Badges
3.0, à la demande du membre, par `phil/open-badges`.

Elle s'adresse à une application qui reconnaît ce que font ses membres — un site communautaire, un site de jeux, une
boutique — et qui veut des badges qu'elle peut recalculer et des membres qui décident de ce qui est public.
`tilttek/badges-bundle` l'intègre à Symfony et garde les badges dans Doctrine DBAL. Une application sans Symfony prend
la bibliothèque seule, avec ses stockages en mémoire ou les siens.

C'est une bibliothèque PHP simple. Ses dépendances sont `tilttek/activity`, le journal que lisent les critères,
`phil/resource`, la référence opaque d'un membre, `psr/clock`, et l'extension `mbstring`. Les membres sont des
références que donne l'application, `member:44` : la bibliothèque ne lit jamais un nom, un courriel ou un compte.

La conception est petite : un `Badge` avec son `Criterion`, tenu par un `Catalog`. `BadgeProjection` lit le journal en
`Contribution` et en `Award` par quatre stockages. `Evaluator` calcule une `Progress`. `Badges` sert l'application :
attributions, progression, `Profile`, `Showcase`, partage, consentements, effacement.

## État

| Partie | État |
|---|---|
| `Badge`, `Tier`, `Catalog` : codes, textes, images, paliers, badges répétables, cachés et révocables, un catalogue à partir de valeurs simples | Implémenté et testé |
| Critères : `Count` (et premières fois), `Total`, `Streak`, `Measure`, `Manual`, `AtLeast` (tous ou certains), périodes et conditions de contexte dans `Scope`, `CriterionReader` | Implémenté et testé |
| `BadgeProjection` et `Evaluator` : contributions une seule fois par activité, attributions et répétitions, retraits, reconstructions sans nouvelle annonce | Implémenté et testé |
| `Badges` : attributions, progression, vue d'ensemble, attributions manuelles, profil, vitrine, titre, partage, consentements des autres applications, effacement | Implémenté et testé |
| Stockages — `ProgressStore`, `AwardStore`, `ProfileStore`, `GrantStore` — et leurs implémentations en mémoire | Implémenté et testé |
| Exceptions sous une seule interface `BadgesException` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Badges et critères](doc/fr/criteria.md) | Les badges, les paliers, chaque sorte de critère, les périodes, les conditions de contexte, le catalogue à partir de valeurs simples. |
| [Attribuer](doc/fr/awarding.md) | La projection, la progression, les badges répétables et révocables, les attributions manuelles, les reconstructions, les observateurs. |
| [Ce que montre un membre](doc/fr/showing.md) | Les badges privés, la vitrine, le titre, le partage, le consentement des autres applications. |
| [Effacement](doc/fr/erasure.md) | Ce qu'efface la suppression d'un membre. |
| [Écrire un stockage](doc/fr/writing-a-store.md) | Les contrats des quatre stockages qu'implémente le stockage d'une application. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application fournit à la bibliothèque : stockages, mesures, critères, observateurs, partage, horloge. |
| [Exceptions](doc/fr/exceptions.md) | `BadgesException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui attribue des badges. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/badges
```

```php
use Tilttek\Badges\BadgeProjection;
use Tilttek\Badges\Badges;
use Tilttek\Badges\Catalog;
use Tilttek\Badges\Evaluator;
use Tilttek\Badges\Store\InMemoryAwardStore;
use Tilttek\Badges\Store\InMemoryGrantStore;
use Tilttek\Badges\Store\InMemoryProfileStore;
use Tilttek\Badges\Store\InMemoryProgressStore;

$catalog = Catalog::fromArray([
    'commenter' => ['name' => 'Commenter', 'criterion' => ['count' => ['verbs' => ['news.commented'], 'times' => 10]]],
    'regular' => ['name' => 'Regular', 'criterion' => ['streak' => ['verbs' => ['site.visited'], 'days' => 7]]],
], 'America/Toronto');
$progress = new InMemoryProgressStore();
$awards = new InMemoryAwardStore();
$evaluator = new Evaluator($catalog, $progress);

$projection = new BadgeProjection($evaluator, $progress, $awards); // handed to tilttek/activity's projections
$badges = new Badges($evaluator, $progress, $awards, new InMemoryProfileStore(), new InMemoryGrantStore(), $clock);

$badges->progress($member, 'commenter'); // 7 of 10
```

## Prérequis

PHP 8.5 ou plus récent, avec l'extension `mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
