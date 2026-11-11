<!-- phil-dev source-sha256=ac1cab0f261c92918fd6a2f49ae100d5e7c2d4ca7c611cf589a97ee94c5236ad -->
# Commandes

| Commande | Fait |
|---|---|
| `tilttek:progression:sweep` | Trouve les séries brisées depuis les derniers gestes des membres et transmet `StreakBroken` pour chacune, une fois : `3 streaks broke.` |
| `tilttek:progression:show <subject>` | Montre où en est un membre (ou un personnage) sur chaque piste (saison, niveau, expérience, niveau suivant, total de toujours) et dans chaque série |

Lancez le balayage une fois par heure à partir du planificateur : les journées des membres se terminent à des heures
différentes. Un sujet qui n'est pas une référence `kind:identifier` échoue avec une erreur.
