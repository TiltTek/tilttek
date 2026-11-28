<!-- phil-dev source-sha256=a00ec253cf1489731c6527be4cfdf41165d2faeae1d8387e76b80754e42ba97b -->
# Installation

```sh
composer require tilttek/encounter
```

Le paquet exige PHP 8.5 ou une version plus récente, et `tilttek/rpg-systems` : les livres de règles trouvent les
règles d'un système de jeu par sa clé ou par son `GameSystem`.

Une application donne à `EncounterPlanner` :

- un `Contract\EncounterStore` qui garde chaque rencontre dans sa base de données ;
- des `Rulebooks`, les règles de difficulté de chaque système de jeu qu'elle offre, ou rien : sans eux, n'importe
  quelle clé de système est acceptée et `assess()` est refusé ;
- un `Contract\EncounterObserver`, pour envoyer ses messages, ou rien ;
- un `Contract\InitiativeHandOff`, l'adaptateur de son suivi d'initiative, ou rien : `launch()` ne fait alors que
  retourner la liste.

Le service, les livres de règles et les règles ne gardent aucun état entre les appels : une seule instance de chacun
sert donc toutes les requêtes d'un *worker* de longue durée.

`Store\InMemoryEncounterStore` garde les rencontres en mémoire, pour les tests.
