<!-- phil-dev source-sha256=d906e74e4bc4d07c4fb611f757df5549b462eeb3955fb2020b44959426d7e389 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : dispositions fixées
pour un secret, cartons uniques, gestes enregistrés et annulés, appels, réclamations sur un carton falsifié ou sur celui
d'un autre joueur, conflits dans le dépôt, et gabarit du carton et d'un lot imprimé dont le rendu est produit avec Twig. Les tests de
scripts, sous `tests/js`, font tourner avec Node.js la fonction d'aide du contrôleur Stimulus. Dans le monodépôt,
Infection maintient le paquet à 100 % des mutants couverts.

## Fuzzing

La cible de fuzzing `label`, sous `tests/Fuzz`, lit n'importe quel texte comme l'étiquette d'un carton imprimé : elle
donne une étiquette qui, écrite puis relue, est la même, ou `InvalidBingo` ; vérifiée contre un jeu qui a un lot
imprimé, elle est refusée ou nomme le carton de son numéro de série. Dans le monodépôt, `composer fuzz` la lance.

## Bancs d'essai

`tests/Benchmark/BingoBench.php` mesure la disposition d'un carton de 5 × 5 tiré de 500 cases, la vérification de chaque
motif d'un carton, et un geste enregistré pour un membre qui détient un carton.
