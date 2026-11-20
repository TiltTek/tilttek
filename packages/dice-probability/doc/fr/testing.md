<!-- phil-dev source-sha256=2b0eb0b0d9c862ae9d84061f6b81f9ed557959825f99ede129683f6b7fe46aee -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite vérifie chaque distribution d'un ensemble de dés contre tous ses jets, chacun jugé par le lanceur de
`tilttek/dice` comme une liste de valeurs avec les mêmes modificateurs. Elle vérifie aussi les explosions, les relances,
les nombres lancés d'abord et l'arithmétique contre des probabilités calculées à la main ; les événements contre
l'exemple de référence et le décompte de leurs jets ; les estimations, leurs germes et leurs limites de dés et de travail des groupes ; et les nombres exacts.
`AgreementPropertyTest` génère des expressions à partir de germes fixes — sommes et différences d'ensembles de dés,
gardés, relancés, et de nombres — et vérifie que les bornes de chaque distribution sont celles que l'expression permet
et que chaque total que le lanceur donne à partir de l'expression analysée est une valeur de sa distribution. Dans le
monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

## Tester du code qui utilise les probabilités

Les résultats sont exacts : comparez les fractions sous forme de texte, `'16/36'`, ou par valeur avec `equals()`. Une
estimation dépend seulement de son germe et de son nombre d'échantillons : passez `new Settings(samples: 200, seed: 42)`
et la même estimation revient, rapidement. Pour forcer une estimation, passez une petite limite de travail :
`new Settings(workLimit: 1)`.
