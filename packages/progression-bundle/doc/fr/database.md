<!-- phil-dev source-sha256=3aaadf7a008ad944a0176c512f6fe1474fabcd96cada4d9a86b7b94120064b5b -->
# Base de données

| Table | Une ligne par | Clé | Contenu |
|---|---|---|---|
| `tilttek_progression_gain` | piste, règle et activité | `track, rule_name, activity` | le sujet, le membre, le montant, le moment, la saison |
| `tilttek_progression_milestone` | sujet, piste, jalon et saison | `subject, track, milestone, season` | le membre, le moment, l'activité |
| `tilttek_progression_streak_gesture` | série et activité | `streak, activity` | le membre, l'unité, le moment |
| `tilttek_progression_streak_freeze` | membre, série et unité | `member, streak, unit` | — |
| `tilttek_progression_streak_state` | membre et série | `member, streak` | les séries en cours et meilleure, le début de la série, la dernière unité active, le fuseau horaire |

`ProgressionSchemaListener` ajoute les tables au schéma que génère l'ORM pour la connexion configurée. Ainsi,
`doctrine:migrations:diff` écrit leur migration et `doctrine:schema:validate` ne demande pas de les supprimer.

Les gains, les jalons, les gestes et les unités gelées sont insérés par une instruction qui n'écrit rien quand la ligne
existe : `ON CONFLICT DO NOTHING` sur PostgreSQL, `INSERT IGNORE` sur MySQL et MariaDB, `INSERT OR IGNORE` sur SQLite ;
ailleurs, une insertion simple, dont la violation d'unicité parvient à l'appelant. Une activité compte ainsi une seule
fois, même quand deux processus l'écrivent. Les totaux sont des sommes, jamais des champs. Les dates sont écrites en
UTC, à la seconde ; les unités sont des numéros de jour ou de semaine.
