<!-- phil-dev source-sha256=3335a9c84470383e486a8abc4fab229ba239ec71afa12f0b0d6021027a7a4af5 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau, sans base de données et sans réseau : les séries et les
séances vivent dans `Store\InMemorySessionStore`, et l'horloge est la `MockClock` de `symfony/clock`, réglée à chaque
moment dont un test a besoin. Les tests traversent un changement d'heure à `America/Toronto`, pour que l'horloge murale
des séances soit vérifiée. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

Une application teste son propre `SessionStore` selon les règles du [stockage](extension-points.md#le-stockage) : rien
pour un identifiant jamais gardé, une nouvelle série ou séance gardée une fois, une version périmée refusée, la version
augmentée de un à chaque écriture, les réponses relues par membre, chaque `SessionQuery` donnant les séances que
`SessionQuery::matches()` accepte, les plus anciennes d'abord, et `countComing()` ne comptant que les séances
planifiées après le moment.
