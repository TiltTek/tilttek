<!-- phil-dev source-sha256=0860a37b76d6975842f6ba3deaf590371f52a9fd2c3cf04570eeb553f4919fce -->
# Installation

```sh
composer require tilttek/session-prep
```

Le paquet exige PHP 8.5 ou une version plus récente, avec `ext-mbstring`, qui compte les caractères des textes.

Une application donne à `SessionPreparer` :

- un `Contract\PreparationStore` qui garde les préparations dans sa base de données ;
- un `Contract\PreparationObserver`, pour envoyer ses messages, ou rien.

Le service ne garde aucun état entre les appels : une seule instance sert donc toutes les requêtes d'un *worker* à
longue durée de vie. `Store\InMemoryPreparationStore` garde les préparations en mémoire, pour les tests.
