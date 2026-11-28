# Installation

```sh
composer require tilttek/fantasy-calendar
```

The library needs PHP 8.5 or later with the `mbstring` extension, and no other package. Its calendars, days and dates
are immutable values, safe to share between the requests of a worker; `Store\InMemoryReminders`, kept for tests, is
the one type with state.
