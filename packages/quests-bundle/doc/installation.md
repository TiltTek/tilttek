# Installation

```sh
composer require tilttek/quests-bundle tilttek/activity-bundle
```

```php
// config/bundles.php
Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
Tilttek\QuestsBundle\TilttekQuestsBundle::class => ['all' => true],
```

The bundle needs PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.5 and a `clock` service — FrameworkBundle registers
one. Install as well, when you need them:

- `phil/almanac`, for quests that come back at computed dates;
- `doctrine/orm`, to add the tables to the schema the ORM generates and so to `doctrine:migrations:diff`;
- `symfony/console`, for the commands;
- `symfony/twig-bundle` and `symfony/translation`, for the templates;
- `phil/privacy-bundle`, to export and erase a member's quests with their account.

## The tables

With the ORM, `doctrine:migrations:diff` writes the migration that creates the five tables of the
[Database](database.md). Without it, create them from `QuestsTables::configure()`.

## The projection

`tilttek/activity-bundle` finds the projection by its tag, `tilttek_activity.projection`. With its live projections,
each recorded activity advances the quests in the same request; otherwise run its catch-up command on a schedule.
After a change of rule, rebuild the projection — `quests` by default — from the moment the change applies.
