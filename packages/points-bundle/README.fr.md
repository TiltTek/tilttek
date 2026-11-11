<!-- phil-dev source-sha256=3667432256e6a4d1e4f78726764fa9da5cc83f644253aa0e28e4c4f5f81b41f1 -->
# tilttek/points-bundle

**Les points des membres d'une application Symfony, gagnés grâce à leur activité et gardés en base de données ligne par
ligne.**

tilttek/points-bundle intègre `tilttek/points` à Symfony. Il garde le registre en partie double dans une table d'une
connexion Doctrine DBAL, avec les lignes de verrou qui font qu'un paiement vérifie le solde un à la fois. Il ajoute les
deux tables au schéma que génère l'ORM, pour que les migrations de l'application les créent. Il construit les devises
et les règles de gain à partir de la configuration, vérifiées à la compilation du conteneur. Il enregistre la
projection qui transforme l'activité des membres en points, que `tilttek/activity-bundle` tient à jour à mesure que le
journal est écrit, rattrape et reconstruit. `Points` donne à l'application les soldes et les historiques, les
attributions, les dépenses, les remboursements et les transferts. Deux commandes font expirer les crédits échus et
montrent l'historique d'un membre. Un module répond aux demandes d'exportation et d'effacement de
`phil/privacy-bundle` pour les points d'un membre.

Il s'adresse à une application Symfony qui récompense ce que font ses membres avec des points qu'ils dépensent en
récompenses ou se donnent, et qui veut que chaque solde s'explique par ses mouvements.

Il requiert les composants DependencyInjection et Config de Symfony, Doctrine DBAL, `tilttek/points` et
`phil/resource`. `tilttek/activity-bundle` alimente les règles de gain ; sans lui, les points ne bougent que par ce que
décide l'application. L'ORM, la Console et `phil/privacy-bundle` sont facultatifs : l'écouteur de schéma, les commandes
et le module de vie privée sont enregistrés seulement quand ils sont installés.

La conception suit celle des autres bundles du monodépôt : un `AbstractBundle` avec sa configuration, des services
déclarés par `PointsServices`, un registre DBAL et un registre en mémoire pour les tests, et des tables ajoutées par un
écouteur de schéma.

## État

| Partie | État |
|---|---|
| Configuration `tilttek_points` : magasin, connexion, devises, devise par défaut, fuseau horaire, règles, projection, taille de lot, type de membre | Implémenté et testé |
| `DbalLedger`, avec les tables `tilttek_points_movement` et `tilttek_points_lock` | Implémenté et testé |
| Les tables dans le schéma que génère l'ORM, pour les migrations | Implémenté et testé |
| La projection étiquetée pour `tilttek/activity-bundle` | Implémenté et testé |
| Commandes `tilttek:points:expire` et `tilttek:points:history` | Implémenté et testé |
| Exportation et effacement d'un membre pour `phil/privacy-bundle` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, activation du bundle, la migration. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `tilttek_points`, avec sa valeur par défaut. |
| [Le registre en base de données](doc/fr/database.md) | Les deux tables, l'insertion idempotente et le verrou par plateforme, les dates. |
| [Gagner grâce à l'activité](doc/fr/earning.md) | La projection avec `tilttek/activity-bundle`, en direct ou par commande, et les reconstructions. |
| [Déplacer des points](doc/fr/moving-points.md) | `Points` dans un contrôleur ou un *handler* : soldes, historiques, dépenses, remboursements, transferts. |
| [Commandes](doc/fr/commands.md) | `tilttek:points:expire` et `tilttek:points:history`. |
| [Vie privée](doc/fr/privacy.md) | L'exportation et l'effacement des points d'un membre. |
| [Points d'extension](doc/fr/extension-points.md) | Les services, les alias et les étiquettes qu'utilise l'application. |
| [Exceptions](doc/fr/exceptions.md) | Ce que le bundle lève. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et comment tester une application qui déplace des points. |
| [Référence](doc/fr/reference.md) | Chaque classe du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/activity-bundle tilttek/points-bundle
```

```yaml
# config/packages/tilttek_points.yaml
tilttek_points:
    currencies:
        points: ~
    timezone: America/Toronto
    rules:
        games: { verbs: ['game.played'], points: 10, cap: { amount: 50, period: week } }
        comments: { verbs: ['news.commented'], points: 2 }
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Points\Points;

final readonly class RedeemReward
{
    public function __construct(private Points $points) {}

    public function __invoke(int $memberId, Reward $reward): void
    {
        $this->points->spend(ResourceReference::of('member', (string) $memberId), $reward->price, 'reward:'.$reward->id.':'.$memberId, 'reward:'.$reward->id);
    }
}
```

Ensuite, `bin/console doctrine:migrations:diff` écrit la migration des tables.

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.5.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
