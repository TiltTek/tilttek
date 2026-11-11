<!-- phil-dev source-sha256=d4216d9f154407ee39a1cdcf7e93b8d13c1cd95e6844c56363a7478ca8a7f4f6 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite vérifie chaque distribution d'un ensemble de dés contre tous ses jets, chacun jugé par le lanceur de
`tilttek/dice` comme une liste de valeurs avec les mêmes modificateurs. Elle vérifie aussi les explosions, les relances,
les nombres lancés d'abord et l'arithmétique contre des probabilités calculées à la main ; les événements contre
l'exemple de référence et le décompte de leurs jets ; les estimations et leurs germes ; et les nombres exacts. Dans le
monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

## Tester du code qui utilise les probabilités

Les résultats sont exacts : comparez les fractions sous forme de texte, `'16/36'`, ou par valeur avec `equals()`. Une
estimation dépend seulement de son germe et de son nombre d'échantillons : passez `new Settings(samples: 200, seed: 42)`
et la même estimation revient, rapidement. Pour forcer une estimation, passez une petite limite de travail :
`new Settings(workLimit: 1)`.
