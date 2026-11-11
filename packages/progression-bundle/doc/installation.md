# Installation

## Requirements

- PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.5 and `doctrine/doctrine-bundle`.
- `tilttek/activity-bundle`, to keep the journal of activity the projection reads.
- Optional: `doctrine/orm` (the tables in the generated schema), `symfony/console` (the commands), `symfony/twig-bundle`
  and `symfony/translation` (the templates), `phil/privacy-bundle` (export and erasure).

## Installing

```sh
composer require tilttek/progression-bundle tilttek/activity-bundle
```

Register both bundles, configure the tracks and streaks ([Configuration](configuration.md)), then let the ORM see the
tables:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

The projection, named `progression`, is found by `tilttek/activity-bundle` by its tag: it follows the journal live,
`tilttek:activity:catch-up progression` catches it up, and `tilttek:activity:rebuild progression --since=…` replays a
period after a rule changes. Schedule `tilttek:progression:sweep` once an hour to tell the broken streaks.
