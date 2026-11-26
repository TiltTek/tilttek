# Changelog

All notable changes to `tilttek/fan-awards` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Edition`, `Category`, `Nominee` and `Phase`: an edition of 1 to 50 categories, with a semifinal or not; per category,
  1 to 10 nominees on a nomination ballot, 2 to 20 semifinalists, 2 finalists to the semifinalists, a ranked final
  method of `phil/poll` and "no award" or not; nominees keyed for the ballots, `no-award` reserved.
- `FanAwards`: editions created in their draft, nominees proposed before and during the nominations, ballots of the
  nominations — 1 to the category's count — and of the semifinal — one — with nominees written in, and ranked final
  ballots; one ballot per voter, category and phase, replaced until the phase ends; the first nominees of an open
  category written in one by one.
- Eligibility: `Contract\NomineeEligibility`, asked of every nominee proposed or written in, its reason given to the
  voter, and `Contract\VoterEligibility`, never a payment nor a purchase, with `Eligibility\AnyNominee` and
  `Eligibility\AnyVoter`; at most 100 nominees per category and phase.
- `advance()`: each voting phase counted by `phil/poll` — the count of choices for the nominations and the semifinal,
  the category's ranked method for the final —, shortlists within their size with ties at the edge included and
  nominees without votes left out, phases of fewer than two options passed on without a vote.
- `PhaseCount` and `Results`: the winners, several when tied, none when "no award" holds the first place alone or
  nobody voted; `preview()` for the organisers once counted, `results()` for everyone once announced, with the detailed
  count of every phase.
- `erase()`: a voter forgotten on their ballots, which stay, anonymous, in the counts.
- `Contract\AwardStore`, with `Store\InMemoryAwardStore`.
- Exceptions under one `FanAwardsException` interface: `AwardRefused` with its `Refusal`, `UnknownAward` and
  `InvalidAward`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
