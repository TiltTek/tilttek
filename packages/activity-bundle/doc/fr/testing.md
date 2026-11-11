<!-- phil-dev source-sha256=07a5c823bf75e68e04fcccf10600368ae1ae7215158df6d05fb92ceea86cefb3 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire exécute le journal et les points de reprise sur SQLite en mémoire — contrat du journal de la
bibliothèque compris. Elle vérifie l'instruction d'insertion de chaque plateforme, l'écouteur du schéma, les commandes,
le module de vie privée et les services que construit la configuration. La suite unitaire envoie aussi les messages des
projections à un bus qui les consigne et les traite deux fois. La suite fonctionnelle démarre une application avec
FrameworkBundle, DoctrineBundle sur SQLite et `phil/privacy-bundle`, une fois avec les projections en direct et une
fois avec les projections par Messenger sur un transport en mémoire. Dans le monodépôt, Infection maintient le bundle à
100 % des mutants couverts.

`phil/privacy-bundle` est une intégration facultative, non installée avec le bundle : ses tests, et son bundle dans
l'application de test, sont sautés avec leur raison quand il manque, et un test vérifie qu'aucun module de vie privée
n'est alors enregistré.

## Tester une application qui enregistre de l'activité

Fixez `store: memory` dans l'environnement de test pour garder le journal dans le processus, ou gardez `doctrine` sur
une base de données de test pour éprouver la véritable insertion. Relisez le journal par le service `Journal` :

```yaml
# config/packages/test/tilttek_activity.yaml
tilttek_activity:
    store: memory
```
