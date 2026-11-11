# Installation

## Requirements

- PHP 8.5 or later, Symfony 8.2 (Config and DependencyInjection), Doctrine DBAL 4.5.
- `tilttek/points` and `phil/resource`, required.
- A `clock` service, which FrameworkBundle registers.
- `tilttek/activity-bundle`, for the earning rules: it keeps the journal of activity and catches up the projection.
- Optional: `doctrine/orm` and DoctrineBundle for the tables in the migrations, `symfony/console` for the commands,
  `phil/privacy-bundle` for the export and the erasure of a member.

## Enabling the bundle

```php
// config/bundles.php
return [
    // ...
    Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
    Tilttek\PointsBundle\TilttekPointsBundle::class => ['all' => true],
];
```

With no configuration, the ledger is kept on the `default` DBAL connection in one currency, `points`, that never
expires, and no rule earns anything: points move only by what the application decides.

## The migration

When the ORM is installed, the bundle adds its two tables to the schema the ORM generates for the ledger's
connection:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, create the tables from `PointsTables::configure()` in the application's own migration tool:

```php
foreach ($platform->getCreateTablesSQL(PointsTables::configure(new Schema())->getTables()) as $sql) {
    $connection->executeStatement($sql);
}
```
