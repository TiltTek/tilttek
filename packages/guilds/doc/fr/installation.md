<!-- phil-dev source-sha256=1b3cb97368066653813d4b21c0bf43531e70d2a0de984d93d5c8157245e59985 -->
# Installation

## Prérequis

- PHP 8.5 ou une version plus récente.
- `phil/resource`, dont les références désignent les guildes et les membres.
- `tilttek/quests`, dont `GuildTeamMembership` implémente le contrat `TeamMembership`.
- Une horloge `psr/clock`, comme `symfony/clock`.
- Facultatif : `tilttek/leaderboards`, pour classer les membres d'une guilde entre eux.

## Installer

```sh
composer require tilttek/guilds
```

## Câbler la bibliothèque

`Guilds` reçoit le magasin de guildes et l'horloge de l'application ; `Goals` reçoit le magasin d'objectifs, le service
`Guilds` et l'horloge. Tout le reste a une valeur par défaut :

```php
use Tilttek\Guilds\Goals;
use Tilttek\Guilds\Guilds;
use Tilttek\Guilds\Limits;

$limits = new Limits(guildsPerMember: 10, requestsPerGuild: 100, openGoals: 10);

$guilds = new Guilds(
    $guildStore,                           // Contract\GuildStore
    $clock,                                // Psr\Clock\ClockInterface
    $limits,
    [$observer],                           // Contract\GuildObserver, told of each change
);

$goals = new Goals($goalStore, $guilds, $clock, $limits, [$observer]);
```

`Store\InMemoryGuildStore` et `Store\InMemoryGoalStore` servent les tests et les applications qui reconstruisent leurs
guildes à chaque démarrage ; une application qui les conserve implémente les deux contrats sur son stockage, comme le
décrivent les [points d'extension](extension-points.md).

`Guilds` et `Goals` ne gardent aucun état d'un appel à l'autre : une seule instance sert toutes les requêtes, en mode
*worker* comme en requête classique. Les magasins et les observateurs qu'on leur donne tiennent aussi cette promesse,
à côté de leur stockage.
