# Changelog

All notable changes to `tilttek/badges-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.3.2-alpha - 2026-11-25

### Security

- `twig/twig` older than 3.31.1 refused, for the security fixes of that version: its sandbox checks the policy in more
  places, and a template can no longer replace the content of a `Markup`.

## 0.3.1-alpha - 2026-11-24

### Changed

- Requires the latest minor of each dependency: `doctrine/orm` 3.8, `doctrine/dbal` 4.6, `doctrine/doctrine-bundle` 3.4,
  `twig/twig` 3.31, `symfony/translation-contracts` 3.8.

## 0.3.0-alpha - 2026-11-15

The return of `BadgeCredentials::issue()` changes.

### Changed

- `BadgeCredentials::issue()` returns the `Phil\OpenBadgesBundle\Application\SecuredCredential` of
  `phil/open-badges-bundle`: its content, a compact JSON Web Token or the JSON of the document with its proof, its
  media type and its file name. `BadgeCredentials::mediaType()` is removed: the media type is the credential's.

### Fixed

- `BadgeCredentials::issue()` issues the badge's award with the highest number, rather than the last award by moment:
  two awards of a repeatable badge sharing a moment, or a badge awarded by hand with an earlier moment than an award of
  the activity, now give the credential of the latest award the member earned.

## 0.2.0-alpha - 2026-11-14

### Changed

- `DbalAwardStore` implements the notices of `AwardStore`: the `notice` column of `tilttek_badges_award` holds
  `awarded` for an award whose event is still due and `revoked` for a withdrawn award whose event is still due. An
  existing table gains the column through the application's next migration.
- `DbalAwardStore::withdraw()` marks the award withdrawn in one conditional statement, whether or not its own event was
  dispatched: an award withdrawn while another process dispatches its `BadgeAwarded` always leaves a `BadgeRevoked`
  due, rather than being deleted with no event left to dispatch.
- `BadgeAwarded` and `BadgeRevoked` are dispatched at least once: a listener that failed is called again when the
  projection is handed the entry again, before the member's next activity, or through `BadgeProjection::deliver()`.
  Listeners must be idempotent.
- The bundle requires `tilttek/badges` `^0.2@dev`, whose notices of `AwardStore` `DbalAwardStore` implements.

### Fixed

- `BadgesPrivacy` implements `Phil\PrivacyBundle\Application\Contract\PersonalDataExporter` and `PersonalDataEraser`,
  the interfaces `phil/privacy-bundle` exposes, and the bundle registers it when they exist: the export and the
  erasure of a member now reach it.

## 0.1.0-alpha - 2026-11-11

### Added

- `DbalProgressStore`, `DbalAwardStore`, `DbalProfileStore` and `DbalGrantStore`, and `Rows`: the four stores of
  `tilttek/badges` on Doctrine DBAL, each row inserted once by a statement that writes nothing when it is already there
  — `ON CONFLICT DO NOTHING`, `INSERT IGNORE`, `INSERT OR IGNORE` —, dates in UTC.
- `BadgesTables` and `BadgesSchemaListener`: the tables `tilttek_badges_contribution`, `tilttek_badges_award`,
  `tilttek_badges_profile` and `tilttek_badges_grant`, added to the schema the ORM generates for their connection.
- `DispatchingObserver`, `BadgeAwarded` and `BadgeRevoked`: the events dispatched when the activity earns a badge or a
  cancellation withdraws one.
- `BadgeCredentials`: the latest award of a member's badge issued as an Open Badges 3.0 credential of
  `phil/open-badges`, its achievement identified by the configured URL and translated in the member's language,
  secured — as a JSON Web Token or with a Data Integrity proof — by the issuer `phil/open-badges-bundle` configures, and
  the media type of what it returns.
- `TilttekBadgesBundle` and its configuration `tilttek_badges`: the store, Doctrine or memory, the connection, the
  timezone of streaks and periods, the badges and their criteria — checked when the container is compiled —, the name of
  the projection, the size of a showcase, the bound of awards one activity brings, the translation domain of the badges'
  texts, the achievement URL of the Open Badges credentials — refused when `phil/open-badges-bundle` is not registered
  —, and the kind of member a data subject stands for. The stores in memory are tagged `kernel.reset`. The projection
  forgets its replay when the kernel resets.
- `BadgesServices`: the stores, the catalog, the evaluator with the application's `Measures` when it defines them,
  `Badges`, and the projection tagged `tilttek_activity.projection`.
- `BadgesExtension` and the templates `@TilttekBadges/showcase.html.twig` and `@TilttekBadges/overview.html.twig`: the
  Twig functions `tilttek_badges_showcase`, `tilttek_badges_overview` and `tilttek_badges_domain`, the title, the
  showcase and the badges shown, and the progress towards each badge, translated in the configured domain.
- `AwardCommand`, `RevokeCommand` and `ListCommand`: `tilttek:badges:award`, `tilttek:badges:revoke` and
  `tilttek:badges:list`.
- `BadgesPrivacy`: the module `tilttek_badges` of `phil/privacy-bundle`, which exports a member's awards, profile and
  consents, and erases them with the contributions of their activity.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
