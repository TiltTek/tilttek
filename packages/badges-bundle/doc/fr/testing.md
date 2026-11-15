<!-- phil-dev source-sha256=f10e1e2905581f128382e6a26d6f0dc3835abee65d029113015501c3b35bd8eb -->
# Tests

## Les tests du bundle

`tests/Unit` vérifie la configuration et les services, les tables et l'écouteur du schéma, les quatre stockages DBAL
sur SQLite en mémoire contre les mêmes contrats que les stockages de la bibliothèque, les événements, les fonctions
Twig, les attestations, le module de vie privée et les commandes. `tests/Functional` démarre une application —
FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle, le bundle de vie privée, le journal d'activité,
`phil/open-badges-bundle` — qui fait gagner, retire, montre, produit le rendu, délivre, exporte et efface.

```sh
vendor/bin/phpunit
```

`tests/Benchmark/ProjectionBench.php` mesure la projection de `tilttek/badges` sur les stockages Doctrine, sur SQLite en
mémoire : une activité enregistrée, puis `deliver()` seul — les deux requêtes qui lisent les attributions dont l'avis
est dû —, sur une table de 1 000 puis de 10 000 attributions.

`phil/open-badges-bundle` et `phil/privacy-bundle` sont des intégrations facultatives, non installées avec le bundle :
leurs tests, et leurs bundles dans l'application de test, sont sautés avec leur raison quand ils manquent, et un test
vérifie qu'aucun module de vie privée n'est alors enregistré.

Chaque mutant qu'Infection produit à partir du code du bundle est tué par un test : le seuil est de 100 % des mutants
couverts.

## Tester une application qui attribue des badges

Fixez `store: memory` dans l'environnement de test, enregistrez des activités dans le `Journal` et faites vos
assertions sur `Badges`. Écoutez `BadgeAwarded` pour vérifier un avis.
