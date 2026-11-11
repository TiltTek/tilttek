<!-- phil-dev source-sha256=eedab6c88acadb960c7932f5edd46da2b2f8dd4e1ff663cfbff2c9969a177854 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, Symfony 8.2 (Config et DependencyInjection), Doctrine DBAL 4.5.
- `tilttek/points` et `phil/resource`, requis.
- Un service `clock`, que FrameworkBundle enregistre.
- `tilttek/activity-bundle`, pour les règles de gain : il garde le journal d'activité et rattrape la projection.
- Facultatif : `doctrine/orm` et DoctrineBundle pour les tables dans les migrations, `symfony/console` pour les
  commandes, `phil/privacy-bundle` pour l'exportation et l'effacement d'un membre.

## Activer le bundle

```php
// config/bundles.php
return [
    // ...
    Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
    Tilttek\PointsBundle\TilttekPointsBundle::class => ['all' => true],
];
```

Sans configuration, le registre est gardé sur la connexion DBAL `default` dans une seule devise, `points`, qui n'expire
jamais, et aucune règle ne rapporte quoi que ce soit : les points ne bougent que par ce que décide l'application.

## La migration

Quand l'ORM est installé, le bundle ajoute ses deux tables au schéma que génère l'ORM pour la connexion du registre :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sans l'ORM, créez les tables à partir de `PointsTables::configure()` dans l'outil de migration propre à
l'application :

```php
foreach ($platform->getCreateTablesSQL(PointsTables::configure(new Schema())->getTables()) as $sql) {
    $connection->executeStatement($sql);
}
```
