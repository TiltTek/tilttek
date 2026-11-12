<!-- phil-dev source-sha256=f8c4b79f20fca9464f0eb79a38d1cf4bfd9fdc7b7de764ce7759d62dc9507fee -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests/Unit tests/Functional
node --test tests/js
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau ni réseau : les dés offerts et les expressions des choix,
les descriptions des faces, les annonces en anglais et en français, la vue du formulaire et la configuration. Le test
fonctionnel démarre une petite application — TilttekDiceBundle avec une pièce, ses libellés en français, et Twig — et
dessine le formulaire avec un jet. Les scripts du contrôleur sont testés avec l'exécuteur de tests de Node. Dans le
monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

## La suite de navigateur

La suite sous `tests/Browser` pilote un Chrome sans interface avec Panther : l'expression suit le choix, les boutons
font varier le nombre de dés, un jet d'une tour de dés est annoncé dans la région polie, les cibles font au moins 44
pixels, rien n'est lu à voix haute par défaut, et le lancer ne bouge pas sous `--force-prefers-reduced-motion`. Elle
s'exécute à part des autres suites, avec un chromedriver dans le chemin et Stimulus parmi les dépendances de navigateur
du kit PHIL UX :

```sh
vendor/bin/phpunit --no-configuration --bootstrap vendor/autoload.php tests/Browser
```

Elle est ignorée quand l'un ou l'autre manque.
