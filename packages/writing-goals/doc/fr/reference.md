<!-- phil-dev source-sha256=ad2ecd55c418a7e851c3735511e35ed3405962dde721ff58a37d64ff467f072f -->
# Référence

| Type | Rôle |
|---|---|
| `WritingGoals` | Garde les objectifs, consigne et corrige les comptes, répond les plans au jour d'aujourd'hui de l'auteur, efface un auteur |
| `Goal` | Une cible en mots ou en pages, d'un premier jour à une échéance, sa courbe, ses jours d'écriture et son fuseau horaire |
| `Day` | Une date sans heure, comptée en jours depuis 1970-01-01 |
| `Unit` | Des mots ou des pages |
| `Curve`, `CurveShape` | Le poids de chaque jour d'écriture ; linéaire, chargée au début, chargée à la fin ou personnalisée |
| `WritingDays` | Les jours de la semaine où l'auteur écrit, moins les dates mises de côté |
| `Progress` | Les comptes de chaque jour |
| `Planner` | Partage ce qui reste entre les jours d'écriture restants |
| `Plan`, `DailyQuota` | Un objectif tel qu'il se présente un jour donné ; le quota d'un jour d'écriture |
| `Event\GoalReached` | Envoyé quand un compte amène un objectif à sa cible |
| `Contract\GoalStore`, `Store\InMemoryGoalStore` | Où se gardent les objectifs |
| `Contract\LogStore`, `Store\InMemoryLogStore` | Où se gardent les comptes |
| `Exception\WritingGoalsException` et ses deux exceptions | [Exceptions](exceptions.md) |
