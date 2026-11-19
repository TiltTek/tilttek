# Scheduling

A game whose countdown reached zero ends when `GameDesk::expire()` is called: by the next move refused, or by the
schedule.

## Symfony Scheduler

```yaml
tilttek_escape_room:
    schedule:
        enabled: true
        frequency: 15 seconds   # how often the games due are looked for
        batch: 100              # how many one run ends at most
```

The schedule `tilttek_escape_room` sends an `ExpireDue` message at the frequency given; `ExpireDueHandler` ends the
games due, the earliest first. The message carries nothing: the games are read from the store when it is handled. Run
its worker:

```sh
php bin/console messenger:consume scheduler_tilttek_escape_room
```

A game ended already, or whose time is no longer up, is left as it is: a message handled twice, or by two workers one
after the other, ends each game once. The game ends at the moment its countdown reached zero, not at the moment the
schedule found it, so the final time is the same whatever the frequency.

## The command

```sh
php bin/console tilttek:escape-room:expire --limit=100
```

ends the games due, as the schedule does, from cron or by hand.
