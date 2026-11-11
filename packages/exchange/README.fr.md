<!-- phil-dev source-sha256=246c20f28ca08c959b2c17de1a46e9d9373675b278a99478763a96c7d3b65643 -->
# tilttek/exchange

**Des récompenses que vos membres achètent avec des points, des points qu'ils se donnent et des objets qu'ils
échangent, en toute sûreté et une seule fois.**

tilttek/exchange gère les trois échanges d'un site ludifié. Un membre échange des points contre une récompense du
catalogue (un rabais, un objet numérique, un accès, une place à un événement) en deux étapes. Une réservation dépense
les points dans le grand livre de `tilttek/points` et retient l'unité. L'application confirme ensuite une fois la
livraison faite, ou elle annule et les points reviennent ; une réservation laissée sans confirmation expire. Une
récompense a son prix dans une devise de points, son stock, sa période de disponibilité, une limite par membre et des
conditions, comme un badge ou un niveau, que l'application vérifie. Les membres peuvent se donner des points et
échanger des objets de leurs collections (des cartes virtuelles, les dés d'un jeu) par offres, contre-offres et
acceptation. Les objets offerts sont retenus dès l'offre ; à l'acceptation, les deux côtés changent de mains dans une
seule transaction, ou rien ne bouge. Des gardes assurent l'équité : aucun échange avec soi-même, un délai pour un
nouveau compte, une allocation par période et un journal des transferts que l'application examine. Chaque étape peut
s'exécuter deux fois sans danger, et les points ne sont jamais convertis en argent.

Elle s'adresse à une application qui laisse ses membres dépenser et partager ce qu'ils gagnent (le programme de
fidélité d'un magasin, un site communautaire, un site de jeu) et qui veut des échanges qui ne perdent jamais un point
ni un objet. `tilttek/exchange-bundle` l'intègre à Symfony et garde tout dans Doctrine DBAL ; une application sans
Symfony prend la bibliothèque seule et écrit ses propres stockages, ou utilise ceux en mémoire.

C'est une simple bibliothèque PHP. Ses dépendances sont `tilttek/points`, le grand livre sur lequel les récompenses et
les transferts déplacent les points, `phil/resource`, les références opaques des membres et des objets, et
`psr/clock`. Les objets vivent dans les collections de l'application, atteintes par un `Inventory` qu'elle implémente.
Les conditions des récompenses et les dates d'adhésion des membres viennent aussi de l'application : la bibliothèque ne
lit ni compte, ni badge, ni niveau.

La conception est sobre : `Redemptions` réserve, confirme, annule et fait expirer les `Reservation` des `Reward` d'un
`Catalog` ; `Transfers` donne des points dans les limites des `Guards` et écrit les `Transfer` dans un `TransferLog` ;
`Trades` ouvre, accepte, refuse, retire, contre et fait expirer des `Offer` gardées par un `OfferStore` ; un
`Announcer` est informé de chaque événement ; `ExchangeErasure` efface un membre.

## État

| Partie | État |
|---|---|
| `Reward`, `Catalog`, `InMemoryCatalog`, `Requirements` : prix, stock, disponibilité, limites et conditions | Implémentés et testés |
| `Redemptions` et `ReservationStore` : réservations en deux étapes, stock et limites vérifiés à l'écriture, expiration | Implémentés et testés |
| `Transfers` et `TransferLog` : points donnés entre membres, le journal | Implémentés et testés |
| `Trades`, `OfferStore` et `Inventory` : offres qui retiennent leurs objets, acceptation en une transaction, contre-offres, expiration | Implémentés et testés |
| `Guards` et `Allowance` : aucun échange avec soi-même, délai pour les nouveaux comptes, allocation par période | Implémentés et testés |
| Événements par un `Announcer` | Implémentés et testés |
| `ExchangeErasure` : l'effacement d'un membre | Implémenté et testé |
| Exceptions sous une seule interface `ExchangeException` | Implémentées et testées |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis et installation. |
| [Récompenses](doc/fr/rewards.md) | Le catalogue, les réservations, les confirmations, les annulations, l'expiration, le stock, les limites et les conditions. |
| [Transferts](doc/fr/transfers.md) | Les points donnés entre membres et le journal. |
| [Échanges](doc/fr/trades.md) | Les offres, les contre-offres, l'acceptation, les objets retenus et l'inventaire. |
| [Gardes](doc/fr/guards.md) | L'échange avec soi-même, les nouveaux comptes, les allocations. |
| [Événements](doc/fr/events.md) | Ce dont l'annonceur est informé, et quand. |
| [Effacement](doc/fr/erasure.md) | Ce que l'effacement d'un membre retire et ce qu'il garde. |
| [Écrire un stockage](doc/fr/writing-a-store.md) | Les contrats des stockages, de la transaction et de l'inventaire. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque : stockages, transaction, inventaire, catalogue, conditions, annonceur, horloge. |
| [Exceptions](doc/fr/exceptions.md) | `ExchangeException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et son seuil de mutation, et comment tester du code qui échange. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/exchange
```

```php
use Phil\Resource\ResourceReference;
use Symfony\Component\Clock\NativeClock;
use Tilttek\Exchange\Reservation\InMemoryReservationStore;
use Tilttek\Exchange\Reservation\Redemptions;
use Tilttek\Exchange\Reward\InMemoryCatalog;
use Tilttek\Exchange\Reward\Reward;
use Tilttek\Points\Currencies;
use Tilttek\Points\Currency;
use Tilttek\Points\Ledger\InMemoryLedger;
use Tilttek\Points\Points;

$clock = new NativeClock();
$currencies = new Currencies(new Currency('points'));
$points = new Points(new InMemoryLedger(), $clock, $currencies);
$redemptions = new Redemptions(new InMemoryCatalog(new Reward('discount-10', 100, stock: 50)), new InMemoryReservationStore(), $points, $currencies, $clock);

$member = ResourceReference::of('member', '44');
$points->grant($member, 150, 'welcome:44');

$redemptions->reserve($member, 'discount-10', 'order:2026-0193:reward'); // 100 points spent, one unit held
$redemptions->confirm('order:2026-0193:reward');                         // delivered: the points stay spent
```

## Prérequis

PHP 8.5 ou plus récent.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
