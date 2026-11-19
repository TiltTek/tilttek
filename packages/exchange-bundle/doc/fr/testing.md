<!-- phil-dev source-sha256=b1e7efb5101d6760bec8fdceef42cab5dab39784d57cacb58c6ceedf0fc52c20 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests unitaires exécutent les magasins DBAL sur SQLite en mémoire par les cas de test de contrat de
`tilttek/exchange`. Ils vérifient aussi les verrous, les réservations d'objets et l'annulation d'un échange avec le
registre sur la même connexion, des moments d'un autre fuseau gardés en UTC, `SpendingPointsHold` par le cas de contrat
que passe toute implémentation de `PointsHold` et face à `Redemptions`, la configuration et les services, les
formulaires, les commandes et le module de vie privée. Le test fonctionnel démarre une petite application avec les
points, les transferts et les échanges, une récompense retenue qui expire sous un autre fuseau par défaut, et un membre
exporté puis effacé. Dans le monodépôt, Infection maintient le bundle à 100 % des mutants couverts.

## Sur MySQL et PostgreSQL

`tests/Integration` fait passer les réservations, les offres et le journal des transferts par les mêmes contrats sur
MySQL 8.4 et PostgreSQL 17, et vérifie ce que SQLite ne montre pas : l'index des réservations échues tel que le serveur
le relit, des moments d'un autre fuseau gardés en UTC, le catalogue gardé et relu, et une ligne déjà présente ignorée
sans annuler la transaction qui l'entoure. Les serveurs sont ceux de `phil/dev-tools`, dans Docker ; les URL sont lues
dans `TILTTEK_EXCHANGE_TEST_MYSQL_URL` et `TILTTEK_EXCHANGE_TEST_POSTGRESQL_URL`, sinon dans `PHIL_TEST_MYSQL_URL` et
`PHIL_TEST_POSTGRESQL_URL`. Sans elles, la suite est ignorée :

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/tilttek-exchange-bundle/tests/Integration
vendor/bin/phil-dev services down
```

La suite supprime puis crée les sept tables du bundle dans la base de test une fois par exécution, et les vide avant
chaque test.

## Tester une application qui fait des échanges

Réglez `store: memory` dans l'environnement de test, avec le `store: memory` propre au bundle des points, et une
horloge que vous contrôlez :

```yaml
# config/packages/test/tilttek_exchange.yaml
tilttek_exchange:
    store: memory
```

Appelez ensuite les services depuis un `KernelTestCase` et lisez les soldes, les magasins et les événements qu'ont
reçus vos écouteurs. Une retenue de points qui vous est propre passe le `PointsHoldTestCase` abstrait des tests du
bundle, sur un membre à qui l'on a accordé 100 points.
