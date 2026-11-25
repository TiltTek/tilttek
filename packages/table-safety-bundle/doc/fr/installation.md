<!-- phil-dev source-sha256=ed52fd1b915b8cddfcab49fe91bdd9d7b99ceeff28c675ee01e9187433a2f94c -->
# Installation

```sh
composer require tilttek/table-safety-bundle
```

```php
// config/bundles.php
Phil\ContentWarningsBundle\PhilContentWarningsBundle::class => ['all' => true],
Tilttek\TableSafetyBundle\TilttekTableSafetyBundle::class => ['all' => true],
```

La recette de `recipe/0.1` enregistre le bundle et copie sa configuration et ses routes ; tant qu'elle n'est pas
publiée dans un dépôt de recettes, écrivez-les à la main :

```yaml
# config/routes/tilttek_table_safety.yaml
tilttek_table_safety:
    resource: '@TilttekTableSafetyBundle/config/routes.php'
```

## Qui participe à une table

Le bundle ne connaît ni les membres de l'application ni ses tables. Implémentez `TableAccess` et nommez le service dans
la configuration ; sans lui, chaque écran est refusé.

```php
use Phil\Resource\ResourceReference;
use Tilttek\TableSafetyBundle\Application\Contract\TableAccess;

final readonly class Tables implements TableAccess
{
    public function participant(ResourceReference $table): ?ResourceReference
    {
        // The current member's reference when they take part in the table, else null.
    }

    public function isGameMaster(ResourceReference $table): bool
    {
        // Whether the current member runs the table.
    }
}
```

```yaml
tilttek_table_safety:
    table_access: App\TableSafety\Tables
```

## Les tables

Avec l'ORM, `doctrine:migrations:diff` écrit la migration des quatre tables de [Base de données](database.md) ; sans
lui, créez-les à partir de `TableSafetyTables::configure()`.

## Les écrans

Les écrans étendent la mise en page de l'application — `base.html.twig` à moins que la configuration en nomme une
autre — et remplissent son bloc `body`. Ils sont construits des recettes `Card`, `Badge`, `Button` et `Alert` d'un kit
de UX Toolkit, comme le kit PHIL UX :

```sh
php bin/console ux:install card badge button alert --kit=phil
```

Le contrôleur Stimulus est fourni dans `assets/` ; avec StimulusBundle de Symfony UX et AssetMapper, il s'active
sous le nom `tilttek--table-safety--signals`.

## Les cartes imprimables en PDF

Les cartes imprimables sont une page à imprimer ; avec `phil/paged-media`, le bundle les écrit aussi en PDF :

```sh
composer require phil/paged-media
```

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-intl`, Symfony 8.2, Doctrine DBAL 4.6, Twig 3.31, avec les sessions, les
formulaires et la protection CSRF de FrameworkBundle. Mercure, la console, l'ORM, `phil/paged-media` et
`phil/privacy-bundle` sont facultatifs.
