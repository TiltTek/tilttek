# Changelog

All notable changes to `tilttek/rating` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.1-alpha - 2026-11-20

### Fixed

- `GameResult::fromArray()` refuses a day or a time that does not exist, `2026-02-30T20:00:00Z` or `24:00:00`, with
  `InvalidRating`: it was carried over to the next month or day.

## 0.1.0-alpha - 2026-11-14

### Added

- `Ratings`: results rated in one system per game and kept for all their members at once, each result counted once
  with `Exception\ResultAlreadyRecorded` for a second recording, friendly results that change nothing, provisional
  ratings by the games played — 10 by default — and by the deviation of an OpenSkill skill, standings that may leave
  them out, the rating of a member, standings of public ratings only, publishing and hiding on the member's choice, a
  replay of a game from its source of results that keeps each member's choice, and the export and erasure of a member.
- `GameResult`: a result of 2 to 64 sides of 1 to 32 members, 256 at most, with a rank per side and draws; duels and
  placings; written and read back as plain values.
- `Elo\EloSystem`, `Elo\EloRating`, `Elo\KFactor` and `Elo\KFactorStep`: Elo in hundredths of a point, a K fixed or
  decreasing by games played and rating, the K of the chess federation, games of several players and teams read by
  pairs of sides with each member's own K, the scale and an optional floor.
- `OpenSkill\OpenSkillSystem`, `OpenSkill\SkillRating` and `OpenSkill\OpenSkillModel`: the Bayesian approximation of
  Weng and Lin, with the Plackett-Luce and the Thurstone-Mosteller models, the growth of the uncertainty before each
  game, the smallest share of a variance kept, the draw margin, mu and sigma in millionths, and standings ordered by
  the conservative estimate, and an optional deviation above which a skill is provisional.
- `MemberRating`, `Visibility` and `RatingChange`: a member's rating as stored — the system, the score in hundredths,
  the games, the highest score, the visibility, private by default, the last result and the version —, written and read
  back as plain values.
- `Contract\Rating`, `Contract\RatingSystem`, `Contract\RatingStore` — keeping the results each game counts beside
  the ratings, with the events of each result not delivered yet — and `Contract\PlaySource`, with
  `Store\InMemoryRatingStore`.
- `Event\RatingChanged` and `Event\PeakReached`, carrying identifiers only and a stable `key()`, for a celebration once
  a member has played the rated games asked for; kept with the result until each was dispatched without an exception,
  the events left dispatched by recording the result again — which rates nothing again — or by `Ratings::deliver()`,
  a failed listener never stopping the others. A replay cancels the events of the game not delivered yet, which it may
  no longer give: `RatingStore::replace()` empties them with the ratings.
- `Exception\RatingException`, with `InvalidRating`, `RatingRefused`, `ResultAlreadyRecorded` and `RatingConflict`.
- A fuzz target for the computation of a rating, and benchmarks of a replay of 10 000 results by each system.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
