# Changelog

All notable changes to `tilttek/table-safety-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## Unreleased

The next version is 0.1.2-alpha.

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
  `twig/twig` 3.31, `symfony/ux-twig-component` 3.8, `symfony/translation-contracts` 3.8.

## 0.1.0-alpha - 2026-11-20

### Added

- The configuration: the store, the connection, the access, the layout, the defaults of a table, the Mercure hub, the
  retention of the signals, the threshold of the stars and wishes, the kit's subdirectory and the kind of a
  participant's reference.
- `TableAccess` and `NoTableAccess`: who takes part in a table and who runs it, from the application.
- The stores on Doctrine DBAL and the four tables, added to the schema the ORM generates by `TableSafetySchemaListener`;
  the participations keep the moment of the first answer, `first_answered_at`, whose migration `doc/upgrading.md`
  describes.
- The forms `ThemeAnswersType`, `SessionZeroType`, `StarsAndWishesType`, `SafetyToolsType` and `TableSettingsType`.
- The screens of the table, the answers, the session zero, the stars and wishes and the game master, built of the
  kit's recipes `Card`, `Badge`, `Button` and `Alert`, working without JavaScript, in English and French. What the
  table agreed is the protective list for the game master, from the first answer and without authors, and the summary
  in whole groups of the answers threshold for the players; the session zero sends a need of safety to the lines and
  veils and the consent checklist.
- The routes of the signals: raising, the feed and marking as seen.
- `MercureSignalObserver` and `SignalSubscriptions`: the signals pushed to the private topics of their table, with
  identifiers only; a hub that fails, by URL or built into the server, is logged.
- The Stimulus controller `tilttek--table-safety--signals`, which sends the signals in the background and announces
  each new one.
- The printable cards of a table — the X-card, the sheet of the lines and veils and that of the consent checklist —,
  blank, as a page without JavaScript and, with `phil/paged-media`, as a PDF by `SafetyCardsPdf`.
- The command `tilttek:table-safety:purge-signals`.
- `TableSafetyPrivacy`: a participant's export and erasure for `phil/privacy-bundle`.
- The Twig functions `table_safety_kit()` and `table_safety_tools()`.
- The Symfony Flex recipe, in `recipe/`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
