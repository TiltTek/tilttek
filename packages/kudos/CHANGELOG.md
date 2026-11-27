# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/kudos` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Kudos`: thanks given by a member to another with one of the application's fixed messages, about an object or not,
  recorded as an activity of the verb `kudos.given` of `tilttek/activity` under an idempotency key — the
  application's, or one made of the giver, the receiver, the message, the object and the giver's day —; thanks given
  again under their key returned without a refusal nor a second event.
- `Refusal` and `KudosRefused`: thanks refused to oneself, to a receiver who declines them, over the giver's daily cap
  counted from midnight in their time zone, and over the limit per receiver in a period, withdrawn thanks counted;
  `refusal()` and `remainingToday()` for a screen to read before giving.
- `Limits`: five thanks a day and three to the same member in seven days by default, each count 1 to 100 and the
  period one hour to 366 days.
- `Message` and `Messages`: the closed list of messages, never a free text, each with a name, a translation key and an
  English text; six default messages, and the application's own added, replaced or taken out.
- Reading: `received()` for the receiver alone and `given()` for the giver, in pages of `Thanks` oldest first,
  withdrawn thanks left out; `givenCount()`, the thanks given by period, the measure of a badge; no total of thanks
  received, no ranking.
- `withdraw()` by the giver and `cancel()` by a moderator, each an entry of its own in the journal; a member erased
  through the journal, the receiver forgotten on the thanks they received.
- `Contract\KudosPreferences`, `Contract\MemberTimeZones` and `Contract\KudosObserver`, with
  `Preferences\AcceptAll` and `TimeZone\SiteTimeZone`; the events `KudosGiven` and `KudosCancelled`, identifiers only.
- Exceptions under one `KudosException` interface: `KudosRefused`, `UnknownMessage` and `InvalidKudos`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
