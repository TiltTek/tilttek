<!-- phil-dev source-sha256=9870a0fb247363f0e1e973024ea25d883f11c6014fc2a1d918eb31b46a6abe23 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les rencontres vivent
dans `Store\InMemoryEncounterStore`, et un test garde une rencontre dont la pioche est dans un ordre connu, pour que
chaque distribution donne les cartes attendues ; le mélange de `start()` s'exécute sur un moteur `Mt19937` à germe. Dans
le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

Une application teste son propre `EncounterStore` selon les règles du [stockage](extension-points.md#le-stockage) : rien
pour un identifiant jamais gardé, une nouvelle rencontre gardée une fois, une rencontre périmée refusée, la version
augmentée de un à chaque écriture, et les piles, les combattants et le jeu relus tels qu'ils ont été gardés. Elle teste
son propre `CardRanking` sur chaque face de son jeu : chacune connue, l'ordre voulu, les égalités et les cartes qui
remélangent.
