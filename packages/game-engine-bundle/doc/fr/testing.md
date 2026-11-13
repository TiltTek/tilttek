<!-- phil-dev source-sha256=2f29be4b9d2fd7c9d06856c3f66bb1d329d4e4df4a2be1ed41ee8224a7a4bea3 -->
# Tests

## Les tests du paquet

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans noyau ni réseau : le magasin de journaux sur SQLite en mémoire —
journaux créés, ajoutés, repris et relus tels qu'ils ont été écrits, en-têtes et révisions, coups lus fenêtre par fenêtre, coups concurrents
et coups faits avant qu'un coup soit repris refusés, colonne qui n'est pas du JSON refusée, tables supprimées —, les
identifiants de siège qui nomment une personne refusés, les tables dans le schéma de leur connexion seulement et le SQL
de MySQL et de PostgreSQL, le cache d'états avec ce que son pool redonne refusé et une révision plus récente jamais
écrasée, le verrou tenu pendant le travail et libéré après un
échec, l'éditeur Mercure et un concentrateur qui échoue, les points de terminaison pour un siège, le public, un lecteur
refusé et une partie qui continue, les commandes, les fonctions Twig et la configuration. `tests/js` vérifie la lecture
d'une mise à jour, avec `node --test`. Dans le monodépôt, Infection maintient le paquet à 100 % des mutants couverts.

`tests/Integration` exécute les mêmes tests du magasin de journaux contre MySQL et PostgreSQL. Chacun lit son URL dans
`TILTTEK_GAME_ENGINE_TEST_MYSQL_URL` ou `TILTTEK_GAME_ENGINE_TEST_POSTGRESQL_URL`, sinon dans `PHIL_TEST_MYSQL_URL` ou
`PHIL_TEST_POSTGRESQL_URL`, que `phil-dev services env` définit, et il est ignoré sans l'une ou l'autre.

`tests/Functional` démarre une application avec FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle, les composants
Twig, un verrou en mémoire et un concentrateur Mercure qui enregistre : les tables sont créées par la commande, une
table est jouée et publiée, lue depuis les points de terminaison, son gabarit rendu avec les recettes du kit quand on
en trouve, son journal vérifié, et aucun coup repris une fois sa partie terminée.

`tests/Benchmark/StorageBench.php` mesure un coup ajouté au journal et un journal de 100 coups relu, sur SQLite en
mémoire, et un état mis dans un pool de tableau et relu : environ 22 µs, 0,34 ms, 9 µs et 13 µs sur PHP 8.5 sans son
cache d'opcodes — le coût du travail propre au bundle, avant le temps d'un serveur.

`tests/Benchmark/LargeTableBench.php` mesure une table de 8 000 coups de 16 000 octets chacun : la vue d'un siège, telle
que `GET /{table}` la lit, et un coup, tous deux sur un état en cache, puis un rejeu depuis la mise en place, fenêtre par
fenêtre — environ 71 µs, 110 µs et 0,54 s.

## Tester le jeu d'une application

Configurez le magasin `memory` dans l'environnement de test, pour qu'aucune base de données ne soit nécessaire, et
donnez un germe aux tables, pour que leur hasard soit le même à chaque exécution :

```yaml
# config/packages/test/tilttek_game_engine.yaml
tilttek_game_engine:
    store: memory
    cache: { pool: ~ }
```

```php
$tables = static::getContainer()->get(GameTables::class);
$tables->open('chess', 'table:1', ['seat:1', 'seat:2'], [], str_repeat('0', 64));
```
