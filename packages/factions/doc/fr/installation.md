<!-- phil-dev source-sha256=1fcd7d757a70836a6596724ef7576089899926799d390748e2aaadb3e0f01b35 -->
# Installation

```sh
composer require tilttek/factions
```

Le paquet exige PHP 8.5 ou une version plus récente, avec `ext-mbstring`, qui compte les caractères des textes. Il exige
`psr/clock`, par laquelle le service lit l'heure de chaque changement.

Une application donne à `Factions` :

- un `Contract\RosterStore` qui conserve les listes et leur historique dans sa base de données ;
- une `Psr\Clock\ClockInterface` : l'horloge de `symfony/clock`, ou toute autre ;
- un `Contract\RosterObserver`, pour envoyer ses messages, ou rien.

Le service ne garde aucun état entre les appels : une seule instance sert donc chaque requête d'un *worker* durable.
`Store\InMemoryRosterStore` garde les listes en mémoire, pour les tests.
