<!-- phil-dev source-sha256=8d0b34ae5e97df858ea1a87ababb6260b274ac3a933bdda3785ed293adfb4dad -->
# Installation

```sh
composer require tilttek/initiative-cards
```

Le paquet exige PHP 8.5 ou une version plus récente et `tilttek/card-deck`, dont il utilise les jeux, les cartes et le
mélange.

Une application donne à `InitiativeTracker` :

- un `Contract\EncounterStore` qui garde les rencontres dans sa base de données ;
- un `Contract\CardRanking`, ou rien pour `StandardRanking`, le jeu de 52 cartes et ses jokers ;
- un `Contract\InitiativeObserver`, pour envoyer ses messages, ou rien ;
- un `Random\Randomizer`, ou rien pour un générateur sur le moteur `Secure`.

Le service ne garde aucun état entre les appels : une seule instance sert donc chaque requête d'un *worker* de longue
durée. Un générateur sur un moteur à germe avance à chaque mélange : il sert aux tests, et le moteur `Secure` est celui
de la production.

`Store\InMemoryEncounterStore` garde les rencontres en mémoire, pour les tests.
