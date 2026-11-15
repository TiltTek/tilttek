# Scheduling

Three tasks wait on the clock: the snapshot of each board at the end of its period, the settlement of each week of a
league, and the gestures that leave each sliding window. Each is idempotent — done once, whoever asks first — and finds
what is due on its own, each board in its timezone, so they run at a steady frequency rather than at a board's
midnight.

## Symfony Scheduler

```yaml
tilttek_leaderboards:
    schedule:
        enabled: true
        frequency: '1 hour'
```

With the schedule enabled, the bundle registers the schedule `tilttek_leaderboards`, which sends a `TakeDue` message at
that frequency, and `TakeDueHandler`, which takes the snapshots due, settles the weeks due and slides the windows. A
worker consumes it:

```sh
bin/console messenger:consume scheduler_tilttek_leaderboards
```

`TakeDue` carries nothing: what is due is read from the stores when it is handled. Enabling the schedule without
`symfony/scheduler` fails when the container is compiled.

## A cron

Without Symfony Scheduler, the commands do the same:

```sh
5 * * * * bin/console tilttek:leaderboards:snapshot && bin/console tilttek:leaderboards:settle && bin/console tilttek:leaderboards:slide
```

## When the scheduler is late

A member who enters a division of a new week settles the weeks still open before it first, in the request: their tier
is right even when no worker ran. The snapshots wait for the next run.
