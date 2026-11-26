<!-- phil-dev source-sha256=269cb048aa64c1b49a4b1cbaa0a84ffe005c8811e444fe8485e06799f4ac4ba4 -->
# Installation

```sh
composer require tilttek/session-scheduler
```

Le paquet exige PHP 8.5 ou une version plus récente, `phil/recurrence`, qui lit les règles et liste leurs occurrences,
et `psr/clock`, dont l'horloge donne l'heure au planificateur.

Une application donne à `SessionScheduler` :

- un `Contract\SessionStore` qui garde les séries et les séances dans sa base de données ;
- une `ClockInterface` PSR-20, comme la `NativeClock` de `symfony/clock` ;
- un `Contract\SessionObserver`, pour envoyer ses messages, ou rien ;
- un `Contract\DateProposer`, pour proposer des dates aux membres, ou rien ;
- le plus grand nombre de séances à venir auxquelles un membre peut dire qu'il vient, de 1 à 100, ou rien pour aucun
  plafond.

Le service ne garde aucun état entre les appels : une seule instance sert chaque requête d'un *worker* de longue durée.
`Store\InMemorySessionStore` garde les séries et les séances en mémoire, pour les tests.
