<!-- phil-dev source-sha256=e8f77c3042d6f5ea3b04014ab95ee32ed0e7084ecc2e62383b367cb44e6fadba -->
# tilttek/activity-bundle

**L'activité des membres d'une application Symfony, enregistrée une seule fois dans la base de données, pour que chaque
récompense la lise.**

tilttek/activity-bundle intègre `tilttek/activity` à Symfony. Il garde le journal et les points de reprise des
projections dans deux tables sur une connexion Doctrine DBAL. Il les ajoute au schéma que génère l'ORM, pour que les
migrations de l'application les créent. Il écrit chaque activité une seule fois par clé d'idempotence, même quand deux
processus l'enregistrent au même moment. Il construit le `Journal` avec l'horloge et les verbes déclarés de
l'application, et trouve les projections et les observateurs par les interfaces qu'ils implémentent. Il tient les
projections à jour à mesure que le journal s'écrit — dans la requête, ou hors de celle-ci par Symfony Messenger — ou
par une commande. Il reconstruit une projection après le changement d'une règle, et répond aux demandes d'export et
d'effacement de `phil/privacy-bundle` pour l'activité d'un membre. Il n'attribue rien de lui-même : les paquets qui
récompensent les gestes, et les projections propres à l'application, lisent le journal.

Il s'adresse à une application Symfony qui enregistre ce que font ses membres et le récompense par des points, des
badges, des niveaux, des quêtes ou des classements, à partir d'un seul registre que l'on peut rejouer.

Il exige DependencyInjection et Config de Symfony, Doctrine DBAL, `tilttek/activity`, `phil/resource` et `psr/clock`.
L'ORM, la Console, Messenger et `phil/privacy-bundle` sont facultatifs : l'écouteur du schéma, les commandes, les
messages des projections et le module de vie privée ne sont enregistrés que s'ils sont installés.

La conception suit celle des autres bundles du monodépôt : un `AbstractBundle` avec sa configuration, des services
déclarés par `ActivityServices`, un stockage DBAL et un stockage en mémoire pour les tests, des tables ajoutées par un
écouteur du schéma.

## État

| Partie | État |
|---|---|
| Configuration `tilttek_activity` : stockage, connexion, verbes, taille des lots, façon dont les projections suivent le journal, sorte de membre | Implémenté et testé |
| `DbalActivityLog` et `DbalCheckpoints`, avec les tables `tilttek_activity_entry` et `tilttek_activity_checkpoint` | Implémenté et testé |
| Les tables dans le schéma que génère l'ORM, pour les migrations | Implémenté et testé |
| Projections et observateurs configurés automatiquement ; projections en direct | Implémenté et testé |
| Projections par Messenger : un message par projection et par entrée, le journal comme boîte d'envoi, les points de reprise comme boîte de réception | Implémenté et testé |
| Commandes `tilttek:activity:catch-up`, `tilttek:activity:rebuild`, `tilttek:activity:status` | Implémenté et testé |
| Export et effacement d'un membre pour `phil/privacy-bundle` | Implémenté et testé |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'activation du bundle, la migration. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `tilttek_activity`, avec sa valeur par défaut. |
| [Le journal dans la base de données](doc/fr/database.md) | Les deux tables, l'insertion idempotente selon la plateforme, les dates, les positions. |
| [Enregistrer l'activité](doc/fr/recording.md) | Enregistrer depuis un écouteur ou un *handler*, annuler, déclarer les verbes. |
| [Projections et commandes](doc/fr/projections.md) | Écrire une projection, les projections en direct, les projections par Messenger, le rattrapage, la reconstruction et l'état. |
| [Vie privée](doc/fr/privacy.md) | L'export et l'effacement de l'activité d'un membre. |
| [Points d'extension](doc/fr/extension-points.md) | Les interfaces et les étiquettes qu'implémente l'application. |
| [Exceptions](doc/fr/exceptions.md) | Ce que lève le bundle. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui enregistre de l'activité. |
| [Référence](doc/fr/reference.md) | Chaque classe du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/activity-bundle
```

```yaml
# config/packages/tilttek_activity.yaml
tilttek_activity:
    verbs: ['news.commented', 'game.*', 'order.paid']
```

```php
use Tilttek\Activity\Activity;
use Tilttek\Activity\Journal;

final readonly class OnCommentPosted
{
    public function __construct(private Journal $journal) {}

    public function __invoke(CommentPosted $event): void
    {
        $this->journal->record(Activity::of('comment:'.$event->id, 'member:'.$event->authorId, 'news.commented', $event->postedAt, 'news:'.$event->newsId));
    }
}
```

Ensuite, `bin/console doctrine:migrations:diff` écrit la migration des deux tables.

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.5.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
