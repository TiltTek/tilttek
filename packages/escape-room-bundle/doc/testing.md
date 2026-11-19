# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit tests cover `GameDesk` — every move, the screens told, the results, the expiries once each, the leaderboard
and its bounds —, the stores in memory and on SQLite through the same scenarios, the lock and the order of the bookings,
the reservations on `phil/reservation` in the database, the configuration and the services it wires, Mercure and the
subscriptions, the schedule, its handler and the command, the privacy module, the tables, the schema listener and the
Twig functions. The functional tests boot a small application — FrameworkBundle, DoctrineBundle on SQLite, TwigBundle
and Twig components, a hub that records what it is given, the recipes of the PHIL UX kit copied where `ux:install` puts
them — and drive the console with and without JavaScript, the hint screen and its celebration, the consent and the
leaderboard; they are skipped when the PHIL UX kit is neither beside the monorepo nor installed.

## Testing an application

Keep everything in memory, reset between two requests:

```yaml
when@test:
    tilttek_escape_room:
        store: memory
        mercure: false
```

and give the services a clock that does not move — `clock` as a `Symfony\Component\Clock\MockClock` —, so that
`$clock->sleep(3_600)` runs a countdown to its end before `GameDesk::expireDue()`.
