<!-- phil-dev source-sha256=076a873f9c1a56ec76505b1656aefe459d8ec4efcc95b379929257ea52e60aa5 -->
# tilttek/exchange-bundle

**Récompenses, dons de points et échanges d'objets entre les membres d'une application Symfony, gardés en base de
données.**

tilttek/exchange-bundle intègre `tilttek/exchange` à Symfony, sur les points de `tilttek/points-bundle`. Il garde les
réservations, le journal des transferts et les offres avec leurs objets dans des tables d'une connexion Doctrine DBAL.
Ces tables sont ajoutées au schéma que génère l'ORM, pour que les migrations de l'application les créent. Le catalogue
des récompenses vit dans la configuration ou dans une table que l'application modifie. Le bundle construit
`Redemptions` à partir de la configuration, le contrat `PointsHold` qui retient, garde et rend les points d'une
récompense réservée, ainsi que `Transfers` et `Trades` quand l'application les active, avec
leurs garde-fous (une allocation par période, un délai pour les nouveaux comptes), vérifiés à la compilation du
conteneur. Les étapes d'un échange s'exécutent dans une seule transaction de la connexion, y compris le registre des
points quand il y est gardé. Les événements parviennent au répartiteur d'événements de l'application, où un écouteur
livre les récompenses. Trois formulaires réservent une récompense, donnent des points et font une offre. Une commande
fait expirer les réservations et les offres, une autre montre le journal des transferts. Un module répond aux demandes
d'exportation et d'effacement de `phil/privacy-bundle` pour les échanges d'un membre.

Il s'adresse à une application Symfony dont les membres dépensent leurs points en récompenses, se les donnent ou
échangent les objets de leurs collections, et qui veut que chaque échange soit écrit une seule fois, en entier ou pas
du tout.

Il requiert les composants DependencyInjection et Config de Symfony, Doctrine DBAL, le répartiteur d'événements PSR,
`tilttek/exchange`, `tilttek/points` et `phil/resource`, ainsi que les services `Points` et `Currencies` que
`tilttek/points-bundle` enregistre. L'ORM, la Console, le composant Form et `phil/privacy-bundle` sont facultatifs :
l'écouteur de schéma, les commandes, les formulaires et le module de vie privée sont enregistrés seulement quand ils
sont installés.

La conception suit celle des autres bundles du monodépôt : un `AbstractBundle` avec sa configuration, des services
déclarés par `ExchangeServices`, des magasins DBAL et des magasins en mémoire pour les tests, et des tables ajoutées par
un écouteur de schéma.

## État

| Partie | État |
|---|---|
| Configuration `tilttek_exchange` : magasin, connexion, catalogue, récompenses, durées de vie, conditions, dates d'inscription, fuseau horaire, transferts, échanges, type de membre | Implémenté et testé |
| `DbalCatalog`, `DbalReservationStore`, `DbalTransferLog`, `DbalOfferStore` et `DbalTransaction`, avec leurs sept tables | Implémenté et testé |
| Les moments stockés en UTC par le type `datetime_utc_immutable` | Implémenté et testé |
| Les magasins DBAL sur SQLite, MySQL 8.4 et PostgreSQL 17 | Implémenté et testé |
| Les tables dans le schéma que génère l'ORM, pour les migrations | Implémenté et testé |
| `PointsHold`, les points d'une récompense réservée retenus, gardés ou rendus, et `SpendingPointsHold` sur `Points` | Implémenté et testé |
| Les événements par le répartiteur de l'application | Implémenté et testé |
| Formulaires `RedeemRewardType`, `TransferPointsType` et `OfferType` | Implémenté et testé |
| Commandes `tilttek:exchange:expire` et `tilttek:exchange:transfers` | Implémenté et testé |
| Exportation et effacement d'un membre pour `phil/privacy-bundle` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, activation du bundle, la migration. |
| [Configuration](doc/fr/configuration.md) | Chaque clé de `tilttek_exchange`, avec sa valeur par défaut. |
| [Récompenses](doc/fr/rewards.md) | Le catalogue, réserver depuis un contrôleur, livrer depuis l'événement, l'expiration planifiée, les points qu'une réservation retient. |
| [Transferts et échanges](doc/fr/transfers-and-trades.md) | Les activer, leurs garde-fous, l'inventaire, les dates d'inscription. |
| [Les échanges en base de données](doc/fr/database.md) | Les tables, les verrous et les réservations d'objets, les insertions idempotentes par plateforme, la transaction. |
| [Événements](doc/fr/events.md) | Écouter les événements des échanges. |
| [Formulaires](doc/fr/forms.md) | Les trois formulaires et leurs données. |
| [Commandes](doc/fr/commands.md) | `tilttek:exchange:expire` et `tilttek:exchange:transfers`. |
| [Vie privée](doc/fr/privacy.md) | L'exportation et l'effacement des échanges d'un membre. |
| [Points d'extension](doc/fr/extension-points.md) | Les services, les alias et les étiquettes qu'utilise l'application. |
| [Exceptions](doc/fr/exceptions.md) | Ce que le bundle lève. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, sur SQLite, MySQL et PostgreSQL, et comment tester une application qui fait des échanges. |
| [Référence](doc/fr/reference.md) | Chaque classe du bundle, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/points-bundle tilttek/exchange-bundle
```

```yaml
# config/packages/tilttek_exchange.yaml
tilttek_exchange:
    rewards:
        discount-10: { price: 100 }
        seat: { price: 250, stock: 30, per_member: 1 }
    transfers:
        enabled: true
        allowance: { limit: 500, period: week }
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Reservation\Redemptions;

final readonly class BookSeat
{
    public function __construct(private Redemptions $redemptions) {}

    public function __invoke(int $memberId, string $orderId): void
    {
        $this->redemptions->reserve(ResourceReference::of('member', (string) $memberId), 'seat', 'order:'.$orderId.':seat');
    }
}
```

Ensuite, `bin/console doctrine:migrations:diff` écrit la migration des tables, et un planificateur lance
`bin/console tilttek:exchange:expire`.

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.6.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
