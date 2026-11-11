# Installation

## Requirements

- PHP 8.5 or later, Symfony 8.2 (Config and DependencyInjection), Doctrine DBAL 4.5.
- `tilttek/activity`, `phil/resource` and `psr/clock`, required.
- A `clock` service, which FrameworkBundle registers.
- Optional: `doctrine/orm` and DoctrineBundle for the tables in the migrations, `symfony/console` for the commands,
  `phil/privacy-bundle` for the export and the erasure of a member.

## Enabling the bundle

```php
// config/bundles.php
return [
    // ...
    Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
];
```

With no configuration, the journal is kept on the `default` DBAL connection, every verb is accepted, and the
projections follow the journal as it is written.

## The migration

When the ORM is installed, the bundle adds its two tables to the schema the ORM generates for the journal's
connection:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Without the ORM, create the tables from `ActivityTables::configure()` in the application's own migration tool:

```php
foreach ($platform->getCreateTablesSQL(ActivityTables::configure(new Schema())->getTables()) as $sql) {
    $connection->executeStatement($sql);
}
```
