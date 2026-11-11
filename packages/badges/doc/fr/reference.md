<!-- phil-dev source-sha256=ed1fa00690ce2dfe313865e3e78c87e1b7199f11d558a16f15b4759dd2af9917 -->
# Référence

| Type | Rôle |
|---|---|
| `Badge` | Une reconnaissance : code, textes, image, critère, répétable, caché, révocable, palier |
| `Tier` | La série et le rang d'un badge |
| `Catalog` | Les badges de l'application, leurs feuilles par chemin, leurs séries, les verbes qu'ils lisent |
| `Progress` | L'avancement d'un membre vers un badge |
| `Standing` | Un badge, la progression d'un membre et le nombre de fois où il le détient |
| `Contribution` | Ce qu'une activité a apporté à un critère d'un badge |
| `Award` | Un badge que détient un membre, avec son numéro, son moment, sa cause et s'il a été attribué à la main |
| `Profile` | Ce que montre un membre : badges, vitrine, titre |
| `Showcase` | Ce que tout le monde peut voir des badges d'un membre |
| `Erasure` | Ce que l'effacement d'un membre a supprimé |
| `Evaluator` | Lit la progression d'un membre à partir des contributions et des mesures |
| `BadgeProjection` | La projection de `tilttek/activity` qui attribue et retire les badges |
| `Badges` | Ce qu'appelle l'application : attributions, progression, profil, vitrine, partage, consentements, effacement |
| `AwardObserver` | Averti quand un badge est gagné ou retiré |
| `Measures` | Les valeurs que l'application mesure pour ses membres |
| `ShareChannel` | L'endroit où un membre partage un succès |
| `Criterion\Criterion`, `Criterion\Leaf`, `Criterion\Reading` | Un critère, un critère qui lit l'activité, ce qu'a apporté l'activité d'un membre |
| `Criterion\Scope` | Les verbes, le contexte et la période que lit une feuille |
| `Criterion\Count`, `Total`, `Streak`, `Measure`, `Manual`, `AtLeast` | Les sortes de critères |
| `Criterion\CriterionReader` | Un critère à partir de valeurs simples |
| `Store\ProgressStore`, `AwardStore`, `ProfileStore`, `GrantStore` | L'endroit où la bibliothèque garde son état |
| `Store\InMemory*Store` | Les stockages en mémoire |
| `Exception\BadgesException` et ses exceptions | Voir [Exceptions](exceptions.md) |
