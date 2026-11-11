<!-- phil-dev source-sha256=9049d35c77b632b8826a19e9ea250ef8b3df08ffcf6c2d81f5791bdd517b67d0 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire couvre la configuration et les services qu'elle enregistre, les tables et l'écouteur du schéma, les
quatre dépôts DBAL sur SQLite en mémoire par les contrats de dépôt de la bibliothèque, le catalogue en base de données,
les quêtes qui reviennent aux dates de l'almanach, les événements, les fonctions Twig, les commandes et le module de vie
privée. La suite fonctionnelle démarre une petite application (FrameworkBundle, DoctrineBundle sur SQLite, TwigBundle,
`phil/privacy-bundle`, `tilttek/activity-bundle` et ce bundle). Elle exécute de bout en bout les quêtes, un duel, une
quête qui revient, les gabarits, les commandes ainsi que l'exportation et l'effacement d'un membre. Elle est sautée là
où le répartiteur d'événements manque. Dans le monodépôt, Infection maintient le bundle à 100 % de mutants couverts.

Les insertions écrites une seule fois sont exécutées sur SQLite ; celles de PostgreSQL et de MySQL sont vérifiées par
leur texte.

## Tester une application qui l'utilise

Réglez `store: memory` dans l'environnement de test, enregistrez des activités par le journal, puis lisez `Quests` :

```yaml
when@test:
    tilttek_quests:
        store: memory
```
