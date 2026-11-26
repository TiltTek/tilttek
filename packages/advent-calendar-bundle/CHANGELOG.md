# Changelog

All notable changes to `tilttek/advent-calendar-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.2-alpha - 2026-11-25

### Changed

- The development requirements accept `phil/privacy-bundle` 0.3 and `phil/ux-kit` 0.6, the versions published on
  2026-11-24, and no earlier ones, so that the package installs on its own beside the published versions.

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

- The configuration: the store, the connection, the entity manager, the member resolver, the layout, the kit's
  subdirectory, the celebration, the calendars and the kind of a member's reference for `phil/privacy-bundle`.
- `MemberResolver`, `NoMember` and `Members`: the current member as a stable reference, never an e-mail address.
- `CalendarCatalogue`, `ConfiguredCalendars` and `Calendars`: the calendars of the configuration, checked when the
  container is built, before those of the database.
- `CalendarRecord`, `DoctrineCalendarCatalogue` and the forms `CalendarType` and `SurpriseType`: the calendars a site
  edits through the ORM, each checked by the library when it is submitted and when it is read.
- `DbalOpeningStore` and its table, added to the schema the ORM generates by `OpeningsSchemaListener`, with a primary
  key that makes an opening happen once and a `delivered` column that keeps an opening's `BoxOpened` due until it was
  dispatched without an exception; `InMemoryOpenings` for tests. The bundle requires `tilttek/advent-calendar`
  `^0.2@dev`, whose `OpeningStore` keeps the delivery of each opening.
- The screen of a calendar and the opening of a box, built of the kit's recipes `Card`, `Badge`, `Button` and `Alert`,
  working without JavaScript, in English and French; a refused opening answers 409 with its reason.
- `Celebrations` and `CelebrationListener`: the first opening `BoxOpened` tells, celebrated by the kit's `Celebration`.
- The Stimulus controller `tilttek--advent-calendar--boxes`, which opens a box in the background, moves the focus to it
  and announces it.
- The commands `tilttek:advent-calendar:list`, `tilttek:advent-calendar:show` and `tilttek:advent-calendar:check`.
- `AdventCalendarPrivacy`: a member's export and erasure for `phil/privacy-bundle`.
- The Twig function `advent_calendar_kit()`.
- The Symfony Flex recipe, in `recipe/`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
