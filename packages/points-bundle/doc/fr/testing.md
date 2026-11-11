<!-- phil-dev source-sha256=f6d28d3318bc9ef1baeb7dbe76a621aebf3fbe2a0477e351af77aef581a12744 -->
# Tests

## Les tests du bundle

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire exécute le registre sur SQLite en mémoire, y compris le contrat de registre de la bibliothèque. Elle
vérifie les instructions d'insertion de chaque plateforme, la ligne de verrou d'un paiement, une course perdue contre un
autre processus, l'écouteur de schéma, les commandes, le module de vie privée et les services que construit la
configuration. La suite fonctionnelle démarre une application avec FrameworkBundle, DoctrineBundle sur SQLite,
`phil/privacy-bundle` et `tilttek/activity-bundle`, et gagne, dépense, fait expirer, exporte et efface des points par
leur intermédiaire. Quand `PHIL_TEST_MYSQL_URL` est défini, la suite d'intégration exécute le contrat de registre sur
MySQL, ainsi que deux paiements à partir du même compte qui se rencontrent, le premier validé
pendant que le second s'apprête à prendre la ligne de verrou. Dans le monodépôt, Infection maintient le bundle à 100 % des mutants couverts.

`phil/privacy-bundle` est une intégration facultative, non installée avec le bundle : ses tests, et son bundle dans
l'application de test, sont sautés avec leur raison quand il manque, et un test vérifie qu'aucun module de vie privée
n'est alors enregistré.

## Tester une application qui déplace des points

Réglez `store: memory` dans l'environnement de test pour garder le registre dans le processus (et le journal
d'activité avec `tilttek_activity.store: memory`), ou gardez `doctrine` sur une base de test pour éprouver la vraie
insertion et les vrais verrous :

```yaml
# config/packages/test/tilttek_points.yaml
tilttek_points:
    store: memory
```

Figez le service `clock` (`MockClock`) pour tester les plafonds, les périodes et l'expiration.
