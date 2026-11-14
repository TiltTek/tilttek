<!-- phil-dev source-sha256=8f9b28e6a2f3c2989b414aebb37d041e11a92c2a5b7ed490e9a14e1b81ae42e9 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau ni base de données : des couleurs lues, avec leur luminance
et leur contraste comparés à des valeurs connues, de chaque côté du seuil linéaire de sRGB ; des skins refusés champ par
champ et relus tels qu'ils ont été écrits ; les skins de base tous lisibles ; l'ordre dans lequel une apparence est
trouvée, un skin que le catalogue n'offre plus écarté, l'aspect à contraste élevé à chacune de ses trois étapes, et les
dés possédés lus dans une vraie étagère de `tilttek/collections` ; l'adaptateur de `tilttek/dice-bundle`, du skin par défaut au
contraste élevé et aux nombres de faces qu'il laisse sans apparence. Les tests de l'adaptateur sont ignorés, avec un
message qui le dit, quand le `tilttek/dice-bundle` installé est plus ancien que 0.2.0-alpha. Dans le monodépôt,
Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/definitions.php` est la cible de fuzzing des définitions de skins : n'importe quel texte donne des skins
lisibles qui s'écrivent comme ils ont été lus, ou est refusé avec `InvalidSkin`, jamais avec une autre exception.
`composer fuzz` l'exécute.

`tests/Benchmark/DiceSkinsBench.php` mesure l'apparence d'un dé, en contraste standard et élevé, et la lecture d'un
catalogue de 256 skins.

## Tester une application

Donnez à `DiceSkins` un `FixedSkinCatalog` et un `InMemorySkinChoiceStore`, ainsi qu'un `OwnedDice` de votre cru qui
retourne les dés dont le test a besoin :

```php
$skins = new DiceSkins(FixedSkinCatalog::withBase(), new InMemorySkinChoiceStore(), $ownedDice);
self::assertSame('#ffffff', $skins->appearance($member, 6, Accessibility::HighContrast)->look->face->hex);
```
