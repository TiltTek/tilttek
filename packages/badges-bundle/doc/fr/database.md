<!-- phil-dev source-sha256=ba943e5fbbfb377da9b8d224f83cfca7d0e6d916fbdadc5214cba7be7f9415ff -->
# Les badges dans la base de données

| Table | Une ligne par | Colonnes |
|---|---|---|
| `tilttek_badges_contribution` | badge, chemin et activité | `badge`, `path`, `activity`, `member`, `quantity`, `occurred_at` |
| `tilttek_badges_award` | membre, badge et numéro | `badge`, `member`, `number`, `awarded_at`, `cause`, `manual` |
| `tilttek_badges_profile` | membre qui montre un badge | `member`, `shown` (JSON), `featured` (JSON), `title` |
| `tilttek_badges_grant` | membre et application | `member`, `client`, `granted_at` |

Une contribution, une attribution ou un consentement est inséré par une instruction qui n'écrit rien quand sa ligne est
déjà présente, sans interrompre la transaction englobante : `ON CONFLICT DO NOTHING` sur PostgreSQL, `INSERT IGNORE`
sur MySQL et MariaDB, `INSERT OR IGNORE` sur SQLite. Sur une autre plateforme, une insertion simple laisse la violation
d'unicité d'une concurrence atteindre l'appelant. Une annulation lit et supprime les contributions de l'activité dans
une seule transaction ; un profil est remplacé dans une seule transaction. Les dates sont écrites en UTC, à la seconde
près.

Les membres sont écrits sous forme de références, `member:44`, jusqu'à 320 octets.
