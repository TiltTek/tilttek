# Changelog

All notable changes to `tilttek/badges-bundle` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

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
  the projection, the size of a showcase, the bound of awards one activity brings, the translation domain of the badges' texts, the achievement URL of the Open
  Badges credentials — refused when `phil/open-badges-bundle` is not registered —, and the kind of member a data subject stands for. The stores in memory are tagged `kernel.reset`. The projection
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
