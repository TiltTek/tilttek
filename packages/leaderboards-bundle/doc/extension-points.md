# Extension points

- **A store.** Alias `Tilttek\Leaderboards\Store\ScoreStore`, `SnapshotStore`, `AppearanceStore` or `LeagueStore` to
  your own service; it must pass the library's contract test case. The scores in Valkey need no code: enable `valkey`.
  With `celebrate_on: score_change`, `LeadWatch` wraps the store of scores the configuration chose; to watch your own,
  decorate `Tilttek\Leaderboards\Store\ScoreStore` with `LeadWatch` the same way.
- **The leads.** Alias `Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore` to your own service; it must keep a
  lead once per board, period and member, even when two processes claim it at the same moment.
- **The Valkey client.** Name a client the application already has in `valkey.client`, or replace
  `Tilttek\Leaderboards\Store\Valkey\RawCommandConnection` with a `ValkeyConnection` of your own.
- **The screens.** Override `@TilttekLeaderboards/league.html.twig` or `teams.html.twig` under
  `templates/bundles/TilttekLeaderboardsBundle/`, or install other recipes of the same names.
- **The boards.** Replace the `Tilttek\Leaderboards\Boards` service — from a database, for instance — and rebuild the
  projection when they change.
- **The observer.** `Leaderboards` and `Leagues` tell `DispatchingObserver`; listen to its events, or decorate it.
- **The values reported.** A listener on the event of the package that keeps a level or a balance calls
  `Leaderboards::report()`.
