# Reference

| Class or service | Role |
|---|---|
| `TilttekLeaderboardsBundle` | The bundle and its `tilttek_leaderboards` configuration |
| `Infrastructure\DependencyInjection\LeaderboardsServices` | The services the configuration wires |
| `Infrastructure\Doctrine\LeaderboardsTables` | The nine tables |
| `Infrastructure\Doctrine\LeaderboardsSchemaListener` | The tables in the schema the ORM generates |
| `Infrastructure\Doctrine\DbalScoreStore` | The contributions and scores on DBAL |
| `Infrastructure\Doctrine\DbalSnapshotStore` | The snapshots and their lines on DBAL |
| `Infrastructure\Doctrine\DbalAppearanceStore` | The members' choices on DBAL |
| `Infrastructure\Doctrine\DbalLeagueStore` | The tiers, memberships and settled weeks on DBAL |
| `Infrastructure\Doctrine\DbalLeadStore` | The leads taken on DBAL |
| `Infrastructure\Doctrine\Rows` | The insertion written once and the reading of a row |
| `Infrastructure\Event\DispatchingObserver`, `SnapshotTaken`, `LeagueSettled`, `TopReached` | A snapshot, a settled week and a member who reached the top as events |
| `Infrastructure\Event\Summit` | How a member reached the top: first place, lead or highest tier, the rules shared by the event and the screen |
| `Infrastructure\Event\CelebrateOn` | When a first place is told and celebrated: at the close of the period or on a score change |
| `Infrastructure\Lead\LeadWatch` | The store of scores that watches the lead, with `celebrate_on: score_change` |
| `Infrastructure\Lead\Lead`, `LeadStore` (public alias), `InMemoryLeadStore` | A lead taken, and where the leads are kept once per board, period and member |
| `Infrastructure\Twig\LeaderboardsExtension` | The Twig functions |
| `Infrastructure\Twig\LeagueView` | What a member's league screen shows |
| `Infrastructure\Twig\CelebrationView` | What a member's celebration says |
| `Infrastructure\Twig\KitComponents`, `Infrastructure\DependencyInjection\KitSubdirectoryPass` | The names of the kit's recipes, after the Toolkit's subdirectory |
| `Infrastructure\Console\SnapshotCommand`, `SettleCommand`, `SlideCommand`, `ShowCommand` | The commands |
| `Infrastructure\Scheduler\LeaderboardsSchedule` (tagged `scheduler.schedule_provider`) | The schedule `tilttek_leaderboards` |
| `Infrastructure\Scheduler\TakeDue`, `TakeDueHandler` (public, tagged `messenger.message_handler`) | The scheduled message and what handles it |
| `Infrastructure\Privacy\LeaderboardsPrivacy` | The export and erasure of a member |
| `Tilttek\Leaderboards\Leaderboards` (public) | The library's facade |
| `Tilttek\Leaderboards\LeaderboardProjection` (public, tagged `tilttek_activity.projection`) | The projection |
| `Tilttek\Leaderboards\League\Leagues` (public) | The leagues: divisions, settlements, tiers |
| `Tilttek\Leaderboards\Boards` (public) | The boards of the configuration |
| `Tilttek\Leaderboards\Store\ScoreStore`, `SnapshotStore`, `AppearanceStore`, `LeagueStore` (public aliases) | The stores |
| `Tilttek\Leaderboards\Store\Valkey\ValkeyScoreStore`, `RawCommandConnection`, `tilttek_leaderboards.valkey_client` | The scores in Valkey, when it is enabled |
| `templates/standing.html.twig` | A standing or a snapshot as one member sees it |
| `templates/division.html.twig` | A member's division, with where each line goes |
| `templates/league.html.twig` | A member's league screen, built of the kit's recipes |
| `templates/teams.html.twig` | A ranking of teams, built of the kit's recipes |
| `templates/celebration.html.twig` | A member's celebration, the block `tilttek_leaderboards_celebration` on the kit's `Celebration` |
