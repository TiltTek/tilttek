<!-- phil-dev source-sha256=f2f071fa63fa21fcec4589e7340cb8926983cec70e350a11122bbda259de8a55 -->
# Référence

| Type | Rôle |
|---|---|
| `Progression` | Positions, saisons passées, jalons, gains, séries, gels, balayage, effacement |
| `ProgressionProjection` | L'activité transformée en expérience et en séries, annulations, reconstructions |
| `ProgressionObserver` | Averti des changements de niveau, des jalons et des séries brisées |
| `Curve`, `Level` | L'expérience à partir de laquelle commence chaque niveau ; où en est l'expérience |
| `Track`, `Tracks`, `Subject` | Une ligne de progression et qui elle récompense ; les pistes d'une application |
| `Milestone`, `ReachedMilestone` | Une étape qui mérite une récompense ; une étape atteinte |
| `LevelChange` | Un sujet qui passe d'un niveau à un autre |
| `Standing`, `SeasonResult` | Où en est un sujet maintenant ; ce qu'il a atteint dans une saison passée |
| `Erasure` | Ce qu'effacer un membre a retiré |
| `Names` | Les noms des pistes, des règles, des jalons et des séries |
| `Experience\ExperienceRule` | De l'expérience pour un geste |
| `Experience\Cap`, `Experience\Period` | Le plus qu'une règle donne à un sujet sur une période |
| `Experience\Gain` | L'expérience qu'une activité a apportée à un sujet selon une règle |
| `Season\Season`, `Season\SeasonSchedule` | Une saison ; dans quelle saison tombe un moment |
| `Season\CalendarSeasons`, `Season\SeasonLength` | Saisons par mois, trimestre ou année |
| `Season\NamedSeasons` | Saisons nommées et datées |
| `Streak\StreakRule`, `Streak\StreakRules` | Une série ; les séries d'une application |
| `Streak\StreakUnit`, `Streak\Grace` | Jours ou semaines ; les unités manquées pardonnées |
| `Streak\StreakCalculator`, `Streak\StreakStatus` | Compter les séries ; où en est un membre dans une série |
| `Streak\StreakKeeper` | Les gestes, les états, les unités gelées et le balayage |
| `Streak\StreakState`, `Streak\StreakGesture`, `Streak\StreakBroken` | L'état d'une série ; une unité active ; une série brisée |
| `Streak\TimezoneResolver`, `Streak\FixedTimezone`, `Streak\ContextTimezone` | Le fuseau horaire de la journée d'un membre |
| `Store\ExperienceStore`, `Store\InMemoryExperienceStore`, `Store\Total` | Où les gains sont gardés ; un total |
| `Store\MilestoneStore`, `Store\InMemoryMilestoneStore` | Où les jalons atteints sont gardés |
| `Store\StreakStore`, `Store\InMemoryStreakStore` | Où les séries sont gardées |
| `Exception\ProgressionException` et ses trois exceptions | [Exceptions](exceptions.md) |
