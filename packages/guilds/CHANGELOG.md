# Changelog

[Français](CHANGELOG.fr.md)

All notable changes to `tilttek/guilds` will be documented in this file.

Updates should follow the [Keep a CHANGELOG](https://keepachangelog.com/) principles.

## 0.1.0-alpha - 2026-11-26

### Added

- `Guild`, `Admission` and `Metadata`: a guild under the application's reference, open or on request, with a name of 1
  to 100 characters, a capacity of 2 to 1000 members — 50 by default — and at most 20 metadata entries.
- `Role`, `Membership` and `JoinRequest`: one founder, administrators and members, each since a moment; requests waiting
  for the founder or an administrator.
- `Guilds`: founding, joining an open guild at once and asking to join a guild on request, withdrawing, approving and
  declining requests, leaving — the founder hands the guild over first —, removing — the founder anyone else, an
  administrator members only —, promoting, demoting, handing the guild over, updating — never below the members it
  holds — and dissolving; nobody removed for being inactive.
- Reading: a guild, a membership, the members by role then arrival, their references for a team or a circle, the
  guilds of a member; erasing a member, a founded guild passed to its earliest administrator or member, or dissolved
  with its only member.
- `Limits`: guilds per member, requests included, waiting requests per guild and open goals per guild, by default 10,
  100 and 10.
- `Goals`, `Goal`, `Contribution` and `GoalProgress`: the group track, a goal of 1 to 1,000,000,000 points an
  administrator opens, before a deadline or not, which members' contributions advance together, counted once under a
  key, until it is reached, closed or past its deadline; the total, the contributors and each member's own share, no
  ranking; points kept when a member leaves or is erased.
- `GuildTeamMembership`: the guilds as the teams of the team quests of `tilttek/quests`; the members' references as the
  circle of `tilttek/leaderboards`, suggested.
- `Contract\GuildStore` and `Contract\GoalStore`, with `Store\InMemoryGuildStore` and `Store\InMemoryGoalStore`.
- `Contract\GuildObserver`, told `GuildChanged` and `GoalReached`, identifiers only.
- Exceptions under one `GuildsException` interface: `GuildRefused` with its `Refusal`, `UnknownGuild` and
  `InvalidGuild`.
- The documentation in `doc/`, one file per topic, indexed by the README.
- `CONTRIBUTING.md`, `SECURITY.md` and `CODE_OF_CONDUCT.md`.
