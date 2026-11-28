<!-- phil-dev source-sha256=efa4d11bd0390e7cc72f0b0c2feb0f67850ac592aa0741fc84c6848914adb646 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente, avec `ext-mbstring`.
- `phil/resource`, dont les références nomment les groupes, les campagnes et les membres.
- `tilttek/rpg-systems`, dont les termes donnent les rôles d'un système.
- Une horloge `psr/clock`, comme `symfony/clock`.

## Installer

```sh
composer require tilttek/party
```

## Câbler la bibliothèque

`Parties` reçoit le dépôt et l'horloge de l'application. Tout le reste a une valeur par défaut :

```php
use Tilttek\Party\Limits;
use Tilttek\Party\Parties;

$limits = new Limits(membersPerParty: 12, partiesPerMember: 10, lootPerParty: 500, notesPerParty: 200);

$parties = new Parties(
    $store,                                // Contract\PartyStore
    $clock,                                // Psr\Clock\ClockInterface
    $limits,
    [$observer],                           // Contract\PartyObserver, told of each change
);
```

`Store\InMemoryPartyStore` sert les tests et les applications qui reconstruisent leurs groupes à chaque démarrage ; une
application qui les conserve implémente `Contract\PartyStore` sur son stockage, comme le décrivent les
[points d'extension](extension-points.md).

`Parties` et `Composition\CompositionAdvisor` ne gardent aucun état entre deux appels : une seule instance sert toutes
les requêtes, en mode *worker* comme en mode requête. Le dépôt et les observateurs qu'on leur donne tiennent aussi cette
promesse, à côté de leur stockage.
