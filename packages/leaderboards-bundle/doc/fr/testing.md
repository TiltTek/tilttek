<!-- phil-dev source-sha256=fbc179f022b49bd68b4b7a21e144616f559e2ee0cd6c6d170a27802ff21da50a -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests unitaires couvrent la configuration et les services qu'elle assemble, les tables et l'écouteur du schéma, les
quatre stockages DBAL sur SQLite par les contrats de la bibliothèque, l'observateur et ses événements, les fonctions
Twig, le module de vie privée, les commandes, le calendrier et son *handler*, les scores dans Valkey et les noms des
recettes du kit. Les tests fonctionnels démarrent une petite application (FrameworkBundle, DoctrineBundle sur SQLite,
TwigBundle et les composants Twig, le bundle de vie privée, le journal d'activité, les recettes du kit PHIL UX copiées là
où `ux:install` les met). Ils vérifient les tables dans le schéma généré, les scores tenus en direct et une annulation,
une valeur rapportée, le gabarit rendu selon le choix de chaque membre, la commande d'instantané et son événement, une
ligue hebdomadaire sur deux semaines (sa division rendue, réglée par la commande puis par le message planifié), l'écran
de ligue et le classement d'équipes construits des recettes du kit, et un membre exporté et effacé. L'écran de ligue est
ignoré quand le kit PHIL UX n'est ni à côté du monodépôt ni installé. Ils sont ignorés quand le répartiteur
d'événements, une dépendance de développement, est absent. Dans le monodépôt, Infection tient le bundle à 100 % de
mutants couverts.

## Sur MySQL et PostgreSQL

`tests/Integration` fait passer les quatre stockages DBAL par les mêmes contrats sur MySQL 8.4 et PostgreSQL 17, et
vérifie ce que SQLite ne montre pas : l'index du classement tel que le serveur le relit, un moment d'un autre fuseau
gardé en UTC, et une ligne déjà présente ignorée sans interrompre la transaction qui l'entoure. Les serveurs sont ceux
de `phil/dev-tools`, dans Docker ; les URL sont lues dans `TILTTEK_LEADERBOARDS_TEST_MYSQL_URL` et
`TILTTEK_LEADERBOARDS_TEST_POSTGRESQL_URL`, sinon dans `PHIL_TEST_MYSQL_URL` et `PHIL_TEST_POSTGRESQL_URL`. Sans elles,
la suite est ignorée :

```sh
vendor/bin/phil-dev services up
vendor/bin/phil-dev services test -- packages/tilttek-leaderboards-bundle/tests/Integration
vendor/bin/phil-dev services down
```

La suite supprime et crée les neuf tables du bundle dans la base de test une fois par exécution, et les vide avant
chaque test.

## Tester une application qui s'en sert

Configurez `store: memory` dans l'environnement de test, enregistrez des activités par le service `Journal` et lisez
`Leaderboards`. Un `MockClock` comme service `clock` avance le temps jusqu'à la fin d'une période avant de prendre son
instantané, ou jusqu'à la fin d'une semaine avant de la régler avec `Leagues::settleDue()`.
