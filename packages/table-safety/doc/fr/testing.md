<!-- phil-dev source-sha256=9f90dd5566dfd2bd47db9e323ad748b2ca4910d5429337dadd82214b1e7f004a -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les limites et les
niveaux, les lignes et voiles, la liste de consentement, les thèmes d'une table et leurs bornes, l'entente et ses
préférences, les questions, gabarits, réponses et pacte de la séance zéro, les signaux et le décompte des points
d'étape, les étoiles et les souhaits et leur sommaire, les réglages et leur stockage sous forme de tableaux, les
stockages en mémoire par `StoresTestCase`, et `TableSafety` avec son export et son effacement. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

## Tester une application qui l'utilise

Construisez `TableSafety` sur les stockages en mémoire et un petit `Catalogue`, et passez les moments explicitement :
chaque méthode qui écrit reçoit le moment où elle se produit.
