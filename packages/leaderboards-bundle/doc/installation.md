# Installation

## Requirements

- PHP 8.5 or later, Symfony 8.2, Doctrine DBAL 4.6 and `doctrine/doctrine-bundle`.
- `tilttek/activity-bundle`, to keep the journal of activity the projection reads.
- Optional: `doctrine/orm` (the tables in the generated schema), `symfony/console` (the commands), `symfony/twig-bundle`
  and `symfony/translation` (the templates), `symfony/ux-twig-component` and the recipes `Card`, `Badge` and `Empty` of
  a UX Toolkit kit (the league screen and the ranking of teams), the recipe `Celebration` of the PHIL UX kit 0.5.0-alpha
  or later (the celebration of the top), `symfony/scheduler` (the schedule), `symfony/cache` with the redis extension,
  Relay or Predis (the scores in Valkey from a DSN) — or the application's own client, valkey-glide-php included —,
  `phil/privacy-bundle` (export and erasure).

## Installing

```sh
composer require tilttek/leaderboards-bundle tilttek/activity-bundle
```

Register both bundles, configure the boards ([Configuration](configuration.md)), then let the ORM see the tables:

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

The projection, named `leaderboards`, is found by `tilttek/activity-bundle` by its tag: it follows the journal live,
`tilttek:activity:catch-up leaderboards` catches it up, and `tilttek:activity:rebuild leaderboards --since=…` replays a
period after a board changes. Enable the schedule, or run `tilttek:leaderboards:snapshot`, `tilttek:leaderboards:settle`
and `tilttek:leaderboards:slide` from a cron, to freeze the periods that ended, settle the weeks of the leagues and
slide the windows ([Scheduling](scheduling.md)).

For the league screen, install the recipes of the kit ([Templates](templates.md#the-recipes-of-the-kit)):

```sh
bin/console ux:install card badge empty --kit=github.com/parler-haut-interagir-librement/ux-kit
```
