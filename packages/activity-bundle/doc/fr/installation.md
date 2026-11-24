<!-- phil-dev source-sha256=47fb12f2c414bcdde50015e64015c6ed6dbb119dc1553b7d9a1824070bb100f8 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, Symfony 8.2 (Config et DependencyInjection), Doctrine DBAL 4.6.
- `tilttek/activity`, `phil/resource` et `psr/clock`, exigés.
- Un service `clock`, qu'enregistre FrameworkBundle.
- Facultatifs : `doctrine/orm` et DoctrineBundle pour les tables dans les migrations, `symfony/console` pour les
  commandes, `phil/privacy-bundle` pour l'export et l'effacement d'un membre.

## Activer le bundle

```php
// config/bundles.php
return [
    // ...
    Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
];
```

Sans configuration, le journal est gardé sur la connexion DBAL `default`, tous les verbes sont acceptés, et les
projections suivent le journal à mesure qu'il s'écrit.

## La migration

Quand l'ORM est installé, le bundle ajoute ses deux tables au schéma que génère l'ORM pour la connexion du journal :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sans l'ORM, créez les tables à partir de `ActivityTables::configure()` dans l'outil de migration de l'application :

```php
foreach ($platform->getCreateTablesSQL(ActivityTables::configure(new Schema())->getTables()) as $sql) {
    $connection->executeStatement($sql);
}
```
