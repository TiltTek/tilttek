<!-- phil-dev source-sha256=d516ccd1fe6c046ad539e8e1ddbeae38aa0f0fa443e5355b42a9200bf5dbd0c9 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

Les tests unitaires vérifient la configuration et les services, les tables et les valeurs d'une ligne, les cinq
stockages sur SQLite en mémoire par des copies des contrats de stockage de la bibliothèque, le catalogue dans la base de
données, les dates calculées, l'inventaire, l'apparence des dés, les événements, les commandes, les fonctions Twig et
le module de vie privée. Le test fonctionnel démarre une petite application — FrameworkBundle, DoctrineBundle sur
SQLite, TwigBundle, le bundle de vie privée et le journal d'activité. Il vérifie les tables dans le schéma généré,
l'objet du jour remis en direct, les événements, un paquet ouvert et vérifié, les gabarits rendus en anglais et en
français, les commandes et l'effacement d'un membre. Dans le monodépôt, Infection maintient le bundle à 100 % des
mutants couverts.

## Tester une application qui l'utilise

Fixez `store: memory` dans l'environnement de test pour garder les collections en mémoire, et remplacez le service
`clock` par une `MockClock` pour placer des activités sur un jour donné. Remplacez l'alias `SeedSource` par une source
de germes fixes pour savoir ce que tire un paquet.
