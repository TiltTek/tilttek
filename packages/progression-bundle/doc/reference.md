# Reference

| Class or service | Role |
|---|---|
| `TilttekProgressionBundle` | The bundle, its configuration and the privacy module |
| `Infrastructure\DependencyInjection\ProgressionServices` | The bundle's services |
| `Infrastructure\Doctrine\ProgressionTables` | The five tables |
| `Infrastructure\Doctrine\ProgressionSchemaListener` | The tables in the schema the ORM generates |
| `Infrastructure\Doctrine\DbalExperienceStore`, `DbalMilestoneStore`, `DbalStreakStore` | The stores on Doctrine DBAL |
| `Infrastructure\Doctrine\Rows` | The insertion written once and the reading of rows |
| `Infrastructure\Event\DispatchingObserver`, `LevelUp`, `LevelDown` | The events |
| `Infrastructure\Twig\ProgressionExtension` | The Twig functions |
| `Infrastructure\Console\SweepCommand`, `ShowCommand` | The commands |
| `Infrastructure\Privacy\ProgressionPrivacy` | Export and erasure for `phil/privacy-bundle` |
| `Tilttek\Progression\Progression` (public) | The members' progression |
| `Tilttek\Progression\ProgressionProjection` (public, tagged `tilttek_activity.projection`) | The projection |
| `Tilttek\Progression\Tracks`, `Tilttek\Progression\Streak\StreakRules` (public) | The tracks and the streaks |
| `Tilttek\Progression\Store\ExperienceStore`, `MilestoneStore`, `StreakStore` (public aliases) | The stores |
| `@TilttekProgression/bar.html.twig`, `@TilttekProgression/streak.html.twig` | [Templates](templates.md) |
