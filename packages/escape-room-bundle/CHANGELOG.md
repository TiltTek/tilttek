# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/escape-room-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.2.0-alpha - 2026-11-25

The erasure of a member deletes what no longer serves and pseudonymises only what a running game or a booking needs; the
reason of each choice is in [Privacy](doc/privacy.md#erasure). A `GameStore` or `ScreenStore` of your own implements
`forget()`, and a `ResultStore` of your own implements `forgetTeam()` in place of `eraseTeam()`.

### Added

- `GameStore::forget()` and `ScreenStore::forget()`, which delete the games and the screens named, on Doctrine DBAL
  by pages of 500 identifiers and in memory.
- `ResultStore::forgetTeam()`, which deletes the results of a team.

### Changed

- `EscapeRoomPrivacy::erase()` deletes the games of the member that are over, their screens, and every result of the
  member; a game still running keeps running under the pseudonym, with the consent to show its result withdrawn; a
  screen the member showed as a host is deleted once its game is over or gone, and keeps the pseudonym while it runs;
  the bookings stay under the pseudonym. The report names `tilttek_escape_room_screen.game` too, and each column as
  `deleted`, or `pseudonymised` while a game runs and for the bookings.
- `GameStore::eraseTeam()` withdraws the consent to show the result of each game it rewrites.

- The development requirements accept `phil/privacy-bundle` 0.3, the version published on 2026-11-24, and no earlier
  one, so that the package installs on its own beside the published version of that bundle.

### Removed

- `ResultStore::eraseTeam()`: a withdrawn result serves nothing, and `forgetTeam()` deletes it.

### Security

- `twig/twig` 3.31.1 or later required, for the security fixes of that version: its sandbox checks the policy in more
  places, and a template can no longer replace the content of a `Markup`.

## 0.1.1-alpha - 2026-11-24

### Changed

- The development requirements accept `phil/privacy-bundle` 0.2, its latest published minor version, and no earlier
  one, so that the package installs on its own beside the published version of that bundle.
- Requires the latest minor of each dependency: `doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4,
  `twig/twig` 3.31, `symfony/ux-twig-component` 3.8, `symfony/service-contracts` 3.8.

## 0.1.0-alpha - 2026-11-20

### Added

- `TilttekEscapeRoomBundle` and the configuration `tilttek_escape_room`: the store, the connection, the rooms or the
  application's catalog, the access, the names of the teams, the reservations and the rules of the rooms, the limit of
  the guesses, the schedule, Mercure, the layout, the size of the leaderboard, the kit, the celebration, the translation
  domain and the kind of a member.
- `Application\GameDesk`: a game started under an identifier the application chooses; each move read from the store,
  played, written back with the moment its countdown reaches zero, and told to the screens; the result of an escape
  recorded; the hint screen kept; expiries once each, the earliest first, by batches of 1 to 1 000; the consent; the
  public leaderboard.
- `Application\Contract\RoomCatalog`, `EscapeRoomAccess`, `TeamNames`, `GameStore`, `ResultStore`, `ScreenStore`,
  `PartyBookings` and `ScreenPublisher`, with `ConfiguredRoomCatalog`, `NoEscapeRoomAccess` and `UnnamedTeams`;
  `Application\Screen`, `ScreenKind`, `StoredGame`, `EscapeRoomTopics` and `Exception\DeskRefused`.
- `EscapeRoomTables` and `EscapeRoomSchemaListener`: the five tables of the games, the results, the screens, the
  bookings and the locks of the sessions, added to the schema the ORM generates; `DbalGameStore`, `DbalResultStore`,
  `DbalScreenStore` and `DbalSeatLedger`, a booking decided and written under the lock of its session.
- `InMemoryGameStore`, `InMemoryResultStore`, `InMemoryScreenStore` and `InMemorySeatLedger`, tagged `kernel.reset`.
- The reservations of the sessions on `phil/reservation`, wired when it is installed, in the database or in memory; the
  application's own otherwise.
- `EscapeRoomSchedule`, `ExpireDue` and `ExpireDueHandler` for Symfony Scheduler; the command
  `tilttek:escape-room:expire`.
- `MercureScreenPublisher`, `ScreenSubscriptions` and `MercureHubPass`: each move told by a private update that names
  the move only, the hub named or detected, the authorization cookie of the game's topic.
- The routes of the console, the hint screen and its state, the consent and the leaderboard; `ConsoleController`,
  `ScreenController`, `ConsentController` and `LeaderboardController`, with `Screens` and `Views`.
- The templates of the console, the hint screen, its state and the leaderboard, built of the PHIL UX kit's recipes
  `Card`, `Badge`, `Button`, `Alert` and `Celebration`; the Twig functions `tilttek_escape_room_kit`,
  `tilttek_escape_room_domain` and `tilttek_escape_room_clock`; the texts in English and French.
- The Stimulus controllers `screen` and `console` of `@tilttek/escape-room`, with the DOM events
  `tilttek-escape-room:moved` and `tilttek-escape-room:finished`.
- `EscapeRoomPrivacy` for `phil/privacy-bundle`: a member's games, results, screens and bookings exported, and
  pseudonymised under one `erased:` reference of the erasure, their results withdrawn from the leaderboard.
- A Flex recipe in `recipe/0.1`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
