<!-- phil-dev source-sha256=c2863be7dc5d900504e8b87dad1e24ebf1c74d1e70f9ec814b41b04ba0cbb3ba -->
# Référence

| Classe ou service | Rôle |
|---|---|
| `TilttekProgressionBundle` | Le bundle, sa configuration et le module de vie privée |
| `Infrastructure\DependencyInjection\ProgressionServices` | Les services du bundle |
| `Infrastructure\Doctrine\ProgressionTables` | Les cinq tables |
| `Infrastructure\Doctrine\ProgressionSchemaListener` | Les tables dans le schéma que génère l'ORM |
| `Infrastructure\Doctrine\DbalExperienceStore`, `DbalMilestoneStore`, `DbalStreakStore` | Les magasins sur Doctrine DBAL |
| `Infrastructure\Doctrine\Rows` | L'insertion écrite une seule fois et la lecture des lignes |
| `Infrastructure\Event\DispatchingObserver`, `LevelUp`, `LevelDown` | Les événements |
| `Infrastructure\Twig\ProgressionExtension` | Les fonctions Twig |
| `Infrastructure\Console\SweepCommand`, `ShowCommand` | Les commandes |
| `Infrastructure\Privacy\ProgressionPrivacy` | Exportation et effacement pour `phil/privacy-bundle` |
| `Tilttek\Progression\Progression` (public) | La progression des membres |
| `Tilttek\Progression\ProgressionProjection` (public, étiqueté `tilttek_activity.projection`) | La projection |
| `Tilttek\Progression\Tracks`, `Tilttek\Progression\Streak\StreakRules` (publics) | Les pistes et les séries |
| `Tilttek\Progression\Store\ExperienceStore`, `MilestoneStore`, `StreakStore` (alias publics) | Les magasins |
| `@TilttekProgression/bar.html.twig`, `@TilttekProgression/streak.html.twig` | [Gabarits](templates.md) |
