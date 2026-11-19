<!-- phil-dev source-sha256=f930ac0bf4ff40b89cdd45a21917ade430b7b715e29a5fc912b8c9c6e1b5d44b -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests unitaires exécutent les stockages DBAL sur SQLite, dans un fichier en journalisation en écriture anticipée avec
une seconde connexion pour l'autre écrivain, par le cas de test de contrat `StoresTestCase` : les entrées gardées et
relues telles qu'elles ont été écrites, les index des soldes, des détenteurs, des opérations et des sessions, un écrivain
refusé après une position qui n'est plus la dernière, un écrivain qui en rencontre un autre entre sa lecture et son
insertion — un middleware des tests exécute alors l'autre —, une opération de la bibliothèque relue deux fois et écrite la
troisième, les mêmes opérations sur le stockage et sur le registre en mémoire de la bibliothèque, un membre effacé dans
chaque table, et les règles gardées et relues. Ils couvrent aussi les tables et l'écouteur du schéma, la configuration et
les services, `LockedLuckTokens` et les verrous qu'il prend et libère, le voter et l'accès par le composant de sécurité,
et le module d'effacement. Le test fonctionnel démarre une petite application avec les verrous activés et efface un
membre par le bundle de vie privée. Dans le monodépôt, Infection maintient le bundle à 100 % des mutants couverts.

## Sur MySQL et PostgreSQL

`tests/Integration` passe le même `StoresTestCase` sur MySQL 8.4 et PostgreSQL 17 — l'autre écrivain sur sa propre
connexion, des références qui ne diffèrent que par la casse gardées distinctes —, et exécute quatre processus qui
octroient au même détenteur de la même table en même temps, sur SQLite, MySQL et PostgreSQL, avec et sans les verrous :
chaque octroi est écrit une fois, à des positions qui se suivent, chaque solde dépassant d'un le précédent ; sous les
verrous, aucun processus n'en rencontre un autre. Les serveurs sont ceux de `phil/dev-tools`, dans Docker ; les URL sont
lues dans `TILTTEK_LUCK_TOKENS_TEST_MYSQL_URL` et `TILTTEK_LUCK_TOKENS_TEST_POSTGRESQL_URL`, sinon dans
`PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`. Sans elles, les tests de serveur sont ignorés :

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/tilttek-luck-tokens-bundle/tests/Integration
vendor/bin/phil-dev services down
```

La suite supprime et crée les deux tables du bundle dans la base de données de test une fois par exécution, et les vide
avant chaque test.

## Le banc d'essai

`tests/Benchmark/DbalLuckTokensBench.php` mesure, sur SQLite en mémoire, un jeton dépensé puis octroyé de nouveau après
10 000 entrées, et le début d'une session pour douze joueurs, à côté du registre en mémoire du banc d'essai de la
bibliothèque. Sur la machine des mesures, une dépense et un octroi prennent ensemble environ 0,19 ms dans la base de
données, contre 18 µs en mémoire ; le début d'une session, qui lit les détenteurs de tout le registre, environ 2,6 ms.

## Tester une application

Réglez `store: memory` dans l'environnement de test, et une horloge que vous contrôlez :

```yaml
# config/packages/test/tilttek_luck_tokens.yaml
tilttek_luck_tokens:
    store: memory
```

Appelez ensuite `LuckTokens` depuis un `KernelTestCase` et lisez les soldes, le registre et les événements qu'ont entendus
vos écouteurs. Un stockage à vous étend le `StoresTestCase` abstrait des tests du bundle.
