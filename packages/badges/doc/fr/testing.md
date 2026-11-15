<!-- phil-dev source-sha256=d6b7de6ad783321e682a40ea8ed72c5f85a74ea7de21c6203f5ff3bba5267537 -->
# Tests

## Les tests de la bibliothèque

Les tests sous `tests/Unit` couvrent chaque classe : les valeurs et leurs limites, chaque critère et le lecteur, le
catalogue, la projection sur un véritable journal de `tilttek/activity` en mémoire — attributions, répétitions,
retraits, attributions manuelles, mesures, reconstructions —, `Badges`, et les contrats des quatre stockages, écrits
comme des cas de test abstraits que chaque implémentation étend.

```sh
vendor/bin/phpunit
```

`tests/Benchmark/ProjectionBench.php` mesure ce que coûte la projection pour une activité enregistrée, sur les
stockages en mémoire — une activité que vingt badges comptés acceptent sans atteindre leur cible, et une qu'aucun badge
ne compte — puis `deliver()` seul, les deux lectures des attributions dont l'avis est dû que `apply()` exécute en
premier, sur des stockages de 1 000 puis de 10 000 attributions.

Chaque mutant qu'Infection produit à partir du code de la bibliothèque est tué par un test : le seuil est de 100 % des
mutants couverts.

## Tester du code qui attribue des badges

Construisez le catalogue, les stockages en mémoire et un `Journal` dont les `LiveProjections` contiennent la
`BadgeProjection`. Enregistrez les activités qu'enregistre le code à tester, et faites vos assertions sur `Badges`. Une
horloge que fixe le test date les attributions manuelles ; un `AwardObserver` du test consigne ce qui est annoncé.
Réutilisez les cas de test abstraits des stockages, `ProgressStoreTestCase` et ses semblables, pour vérifier les
stockages propres à une application.
