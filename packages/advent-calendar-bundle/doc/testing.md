# Testing

## The bundle's tests

```sh
composer install
vendor/bin/phpunit --no-configuration tests
node --test tests/js
```

The unit tests cover the configuration and the services it wires, the calendars of the configuration, the member and
its guard, the celebrations, the boxes of a visitor, the calendar records, the store of the openings on SQLite and in
memory through one contract — two connections opening one box grant it once —, the catalogue of the database and the
schema listener, and the privacy module. The functional tests boot a small application — FrameworkBundle with sessions,
forms and CSRF, DoctrineBundle with the ORM on SQLite, TwigBundle and Twig components, the recipes of the PHIL UX kit
copied where `ux:install` puts them, a clock on the 3rd of December 2026 — and walk the screen: a visitor who sees no
surprise, a member who opens a box with a plain form and in the background and is celebrated once, a refused opening, a
same-day calendar, the guards, a calendar of the database, the form, and the commands. They are skipped when the event
dispatcher, a development dependency, or the PHIL UX kit is missing. The JavaScript tests cover the reading of the
server's answers. In the monorepo, Infection holds the bundle at 100 % of covered mutants.

## Testing an application that uses it

Configure `store: memory` in the test environment, register a `MemberResolver` the test drives, and a `MockClock` as the
`clock` service set on a day of the calendar. Assert on the surprises your listener of `BoxOpened` granted.
