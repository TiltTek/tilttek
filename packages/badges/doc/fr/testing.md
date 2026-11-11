<!-- phil-dev source-sha256=be7ca29adce882775ed5958c80dcdf62067fa4a31a140dc93a72a02e391ea39c -->
# Tests

## Les tests de la bibliothèque

Les tests sous `tests/Unit` couvrent chaque classe : les valeurs et leurs limites, chaque critère et le lecteur, le
catalogue, la projection sur un véritable journal de `tilttek/activity` en mémoire — attributions, répétitions,
retraits, attributions manuelles, mesures, reconstructions —, `Badges`, et les contrats des quatre stockages, écrits
comme des cas de test abstraits que chaque implémentation étend.

```sh
vendor/bin/phpunit
```

Chaque mutant qu'Infection produit à partir du code de la bibliothèque est tué par un test : le seuil est de 100 % des
mutants couverts.

## Tester du code qui attribue des badges

Construisez le catalogue, les stockages en mémoire et un `Journal` dont les `LiveProjections` contiennent la
`BadgeProjection`. Enregistrez les activités qu'enregistre le code à tester, et faites vos assertions sur `Badges`. Une
horloge que fixe le test date les attributions manuelles ; un `AwardObserver` du test consigne ce qui est annoncé.
Réutilisez les cas de test abstraits des stockages, `ProgressStoreTestCase` et ses semblables, pour vérifier les
stockages propres à une application.
