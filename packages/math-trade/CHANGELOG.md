# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/math-trade` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Participant`, `Offer`, `Want` and `WantGroup`: the immutable trade list — participants, their offers, the game each
  copy is, and wishes most wished first, a group being any one of several offers at one priority —, with identifiers
  of 1 to 100 bytes of UTF-8 without a control character.
- `TradeList`: the list checked as a whole — unique participants and offers, wishes for existing offers of other
  participants, no offer wished twice — and against `Limits`: participants, offers, offers per participant, wishes per
  offer, size of a group, wished offers and length of a JSON document.
- `TradeSolver`: the most offers traded, then the best sum of priorities, as a minimum-cost cover by cycles found by a
  sparse minimum-cost assignment; a participant who wishes for a game from several offers, with several copies
  wished for, receives it once at most; a seed of up to 256 bytes breaks ties, so that the same list and seed give the
  same result whatever the order of the list.
- `TradeResult`, `TradeCycle`, `Trade` and `Swap`: the loops of trades, every trade, what each participant sends to
  whom and receives from whom, and the offers kept.
- `TradeListJson`: the lists written and read as JSON, on the documented `tilttek/math-trade` format, version 1, with a
  strict reader bounded by the length of the document.
- `Exception\MathTradeException`, with `InvalidTradeList`, `LimitExceeded` and `InvalidSeed`.
- A benchmark of 1 000 offers, and a fuzz target for the JSON reader.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
