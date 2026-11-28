<!-- phil-dev source-sha256=954b25d1083178ac42a0bf69984eea7ff35432e2543d356784d4019023a00828 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : ratios exacts et chaque mode d'arrondi, bornes, cellules
carrées, règles de diagonale, lignes, zones, anneaux et champs de vision sur les carrés et les hexagones, conversions
des coordonnées, hexagone le plus proche d'une position fractionnaire, grilles sur des images et leur alignement,
cartes sans grille, et noms de fichier. Des tests de propriétés tirent des coordonnées de germes fixes et vérifient que
les conversions vont et reviennent, que le centre d'une cellule se trouve dans cette cellule sur chaque sorte de
grille, que les lignes sont des chaînes de voisins aussi longues que la distance, que les rotations conservent les
distances, et que les zones et les anneaux ont la taille que donne leur rayon. Dans le monodépôt, Infection maintient la
bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/file-name.php` est la cible de fuzzing de `MapHints` : tout nom donne des indices dans leurs bornes, les
mêmes à chaque fois, jamais une erreur. `composer fuzz` l'exécute.

## Bancs d'essai

`tests/Benchmark/GridBench.php` trouve mille fois l'hexagone sous un pointeur sur une carte numérisée, trace une ligne
de 100 pas, et calcule un champ de vision au plus grand rayon avec des murs. Sur PHP 8.5 : environ 25 ms pour les mille
pointeurs, moins de 0,1 ms pour la ligne, environ 80 ms pour le champ de vision de rayon 30.

## Tester du code qui utilise des grilles

Les types sont des valeurs : construisez-les dans un test comme en production. Un champ de vision prend une fermeture,
donc un test lui donne les murs sous forme d'ensemble de clés :

```php
$walls = ['1,0' => true, '2,-1' => true];
$seen = new Axial(0, 0)->fieldOfView(3, fn (Axial $hex): bool => isset($walls[$hex->key()]));
```
