# Earning from the activity

The bundle registers `PointsProjection`, tagged `tilttek_activity.projection`: `tilttek/activity-bundle` finds it with
its other projections. With its `live_projections` (the default), each activity recorded through the `Journal` earns
its points in the same request:

```php
$journal->record(Activity::of('game:812:member:44', 'member:44', 'game.played', $playedAt, 'game:812'));
$points->balance(ResourceReference::of('member', '44')); // 10
$journal->cancel('game:812:member:44');                  // the points are reversed
```

Without live projections, a scheduled task catches it up:

```sh
bin/console tilttek:activity:catch-up points
```

## When a rule changes

Change the rule in the configuration, deploy, then rebuild the period it applies to:

```sh
bin/console tilttek:activity:rebuild points --since=2026-11-01
```

The derived movements — earned, reversed, expired — from that date on are forgotten and the journal of the period is
replayed under the new rules; spendings, transfers, grants and refunds stay. Run `tilttek:points:expire` afterwards
when a currency expires.

The verbs the rules read must be among the verbs `tilttek_activity` declares, if it declares any.
