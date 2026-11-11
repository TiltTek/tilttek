<!-- phil-dev source-sha256=1e476d6308ca97cbef97f997f4c727f1dad4b8920ac32aa17a96357ec4fd180a -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite vérifie les limites de chaque valeur, la révélation contre des germes connus, chaque champ d'une preuve
altérée, les codes de chaque dé du d2 au d20, et les textes de tirage. Dans le monodépôt, Infection maintient la
bibliothèque à 100 % des mutants couverts. Deux cibles de fuzzing nourrissent les lecteurs des codes de boutique et des
textes de tirage avec n'importe quelle entrée : ce qui se lit se réécrit en le même code ou les mêmes faces, et tout le
reste est refusé avec `InvalidCode`.

```sh
composer fuzz -- shop-code draw-text
```

Les bancs d'essai mesurent la révélation et la vérification d'une boîte de dix tables d20, ainsi que l'écriture et la
lecture des codes.

## Tester du code qui utilise la bibliothèque

Un moteur avec germe donne le même germe à chaque exécution : `PendingDraw::open(new Randomizer(new Xoshiro256StarStar(1)))`,
ou `PendingDraw::restore()` avec un germe fixe. Les faces que donne un germe ne changent jamais, puisque les dés de
`tilttek/dice` reposent sur les moteurs avec germe de PHP. Les résultats de faces choisies viennent de
`LootResult::ofFaces()`.
