# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

The unit tests cover the configuration and the services it wires, the tables, the four DBAL stores on SQLite through
the library's contracts, the topics, the push to a hub and the subscriptions, the forms and their conversions, the
privacy module and the purge command. The functional tests boot a small application — FrameworkBundle with sessions,
forms and CSRF, DoctrineBundle on SQLite, TwigBundle and Twig components, the catalogue of
`phil/content-warnings-bundle`, a hub that records what it is given, the recipes of the PHIL UX kit copied where
`ux:install` puts them — and walk the screens: a viewer refused, a signal raised with a plain form and from a script, a
signal refused, the answers, the session zero and the stars and wishes saved and refused, and the game master who asks
for a check-in, marks signals as seen and changes the settings. They are skipped when the event dispatcher, a
development dependency, or the PHIL UX kit is missing. In the monorepo, Infection holds the bundle at 100 % of covered
mutants.

## Testing an application that uses it

Configure `store: memory` in the test environment, register a `TableAccess` the test drives, and a `MockClock` as the
`clock` service.
