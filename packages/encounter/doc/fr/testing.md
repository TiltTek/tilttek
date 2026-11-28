<!-- phil-dev source-sha256=9c1d5dbdc32ecc507f59b1118ca9fe627209b3ee49bfe4c016335c83f6728409 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les rencontres vivent
dans `Store\InMemoryEncounterStore`, et deux systèmes de jeu inventés — l'un par budget, l'autre par niveau — donnent
les règles de difficulté. Les tests couvrent chaque borne à sa limite et un cran au-delà, chaque placement d'un
participant, chaque refus du service, et les évaluations des deux sortes de règles, multiplicateurs et tailles de groupe
compris. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## La cible de fuzzing

`tests/Fuzz/declaration.php` fournit n'importe quel JSON à `Difficulty\Declaration::read()`, puis évalue une rencontre
avec les règles obtenues : chaque entrée est soit refusée avec les exceptions de la bibliothèque, soit donne une
évaluation conforme à sa forme. `composer fuzz -- declaration` l'exécute dans le monodépôt ; ses germes sont sous
`tests/Fuzz/corpus/declaration`.

## Tester les propres pièces d'une application

Une application teste son propre `EncounterStore` selon les règles du [magasin](extension-points.md#le-magasin) : rien
pour une rencontre jamais gardée, une nouvelle rencontre gardée une fois, une rencontre périmée refusée à
l'enregistrement et au retrait, la révision augmentée de un à chaque écriture, et les participants relus dans leur
ordre.

Elle teste ses propres `DifficultyRules` avec un groupe et des adversaires dont elle calcule à la main le coût et les
seuils, à chaque seuil de palier et un cran en dessous, et son `InitiativeHandOff` en lançant une rencontre et en lisant
le suivi.
