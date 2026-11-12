<!-- phil-dev source-sha256=8c0c9f6d04d725d3307ac814ce9f0449ed3ca0194aa374b3d96bb592490857e3 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\WritingGoals\Exception\WritingGoalsException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidGoal` | Un identifiant, une cible, une durée en jours, un fuseau horaire, une date, une courbe, un jour de la semaine, un compte ou un jour sans écriture est refusé, ou un compte est consigné sur un jour qui n'est pas venu | `A goal lasts 1 to 3660 days, its first and last included; from 2026-11-01 to 2026-10-31 is not one.` |
| `UnknownGoal` | Un objectif est demandé par un identifiant que le magasin ne connaît pas | `No goal has the identifier "novel-2026".` |
