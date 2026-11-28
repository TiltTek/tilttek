<!-- phil-dev source-sha256=2e2e43d33c249bc6fe5473c921c63b12f23eac0f8a9ccf0e1c23285582651101 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests/Unit
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau, sur l'inventaire en
mémoire et le `MockClock` de `symfony/clock` : les codes, les clés, les quantités et leurs limites ; les ingrédients par
objet et par étiquette, les outils ; les recettes qui combinent ou font évoluer et chaque recette refusée ; le livre lu
depuis des valeurs simples, réécrit, et chaque valeur refusée ; les objets protégés ; les aperçus et ce qui manque ; les
objets mis de côté par code, par choix, par étiquette, l'étiquette la plus rare d'abord ; les sélections ; les
fabrications une seule fois par clé, la clé redonnée une fois les objets partis, une clé d'une autre fabrication, une
fabrication appliquée en même temps sous sa clé ; les répétitions ; ce qui est fabricable et le plus proche ; les
limites des avoirs ; et les événements signalés. Dans le monodépôt, Infection maintient la bibliothèque à 100 % des
mutants couverts.

La cible de fuzzing `recipe-book`, sous `tests/Fuzz`, lit n'importe quel JSON comme un livre : il est refusé avec les
exceptions de la bibliothèque, ou il se réécrit avec les mêmes valeurs et aucune de ses recettes n'est fabricable depuis
un inventaire vide. `composer fuzz -- recipe-book` l'exécute depuis le monodépôt.

Le banc d'essai, sous `tests/Benchmark`, remplit le livre jusqu'à sa limite — 1 000 recettes de quatre ingrédients, dont
deux demandés par une étiquette — pour un détenteur de 500 objets, et mesure ce qui est fabricable, ce qui est le plus
proche, un aperçu et une fabrication.

## Tester le code qui fabrique

`Store\InMemoryInventory`, à qui l'on donne les étiquettes de chaque objet, et un `MockClock` donnent une installation de
fabrication complète dans un test : `put()` donne des objets à un détenteur, `quantity()` les relit après une
fabrication. Un observateur qui enregistre les `Crafted` dont il est informé vérifie ce que l'application relaie.
