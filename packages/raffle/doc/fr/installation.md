<!-- phil-dev source-sha256=7f5786ea5c2872e6afe9854b799cf0d1997451cd42cee3e50cc4bbc097d114a1 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-sodium`.
- `phil/url` 0.1, qui vérifie les adresses des relais.
- `psr/clock` et `psr/event-dispatcher` : l'horloge et le répartiteur de `RaffleDrawn`, tous deux facultatifs.
- `symfony/http-client-contracts`, et un client HTTP comme `symfony/http-client` pour l'adaptateur drand.

## Installer

```sh
composer require tilttek/raffle symfony/http-client
```

## Brancher la bibliothèque

```php
use Tilttek\Raffle\Drand\{DrandBeacon, DrandChains};
use Tilttek\Raffle\Raffles;

$raffles = new Raffles(
    $store,              // Contract\RaffleStore: the raffles and their entrants
    $beacon,             // Contract\RandomnessBeacon: needed for a raffle with a prize; null without one
    $dispatcher,         // a PSR-14 dispatcher, told of each draw; null to dispatch nothing
    $clock,              // a PSR-20 clock; the system's by default
    $randomizer,         // the source of the organisers' seeds; the system's secure source by default
    margin: 30,          // the seconds between the closing of the entries and the beacon round
);

$beacon = new DrandBeacon($httpClient, DrandChains::quicknet(), DrandChains::RELAYS);
```

Sans balise, `Raffles` ne tient que des tirages sans enjeu. `Raffles`, `RaffleVerifier` et `DrandBeacon` ne gardent
rien entre deux appels : une seule instance sert donc toutes les requêtes d'un *worker* ; `InMemoryRaffleStore` garde
tout dans le processus, pour les tests et les scripts.
