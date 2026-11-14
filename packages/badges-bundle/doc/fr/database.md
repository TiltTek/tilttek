<!-- phil-dev source-sha256=a6a64122ef7294ea1294fcfc2d6fb1bedfb1d75ef475fb51b9b8feb8b5d25e79 -->
# Les badges dans la base de données

| Table | Une ligne par | Colonnes |
|---|---|---|
| `tilttek_badges_contribution` | badge, chemin et activité | `badge`, `path`, `activity`, `member`, `quantity`, `occurred_at` |
| `tilttek_badges_award` | membre, badge et numéro | `badge`, `member`, `number`, `awarded_at`, `cause`, `manual`, `notice` |
| `tilttek_badges_profile` | membre qui montre un badge | `member`, `shown` (JSON), `featured` (JSON), `title` |
| `tilttek_badges_grant` | membre et application | `member`, `client`, `granted_at` |

Une contribution, une attribution ou un consentement est inséré par une instruction qui n'écrit rien quand sa ligne est
déjà présente, sans interrompre la transaction englobante : `ON CONFLICT DO NOTHING` sur PostgreSQL, `INSERT IGNORE`
sur MySQL et MariaDB, `INSERT OR IGNORE` sur SQLite. Sur une autre plateforme, une insertion simple laisse la violation
d'unicité d'une concurrence atteindre l'appelant. Une annulation lit et supprime les contributions de l'activité dans
une seule transaction ; un profil est remplacé dans une seule transaction. Les dates sont écrites en UTC, à la seconde
près.

Les membres sont écrits sous forme de références, `member:44`, jusqu'à 320 octets.

La colonne `notice` d'une attribution contient l'avis que la projection doit encore à son observateur : `awarded` tant
que l'événement d'une attribution gagnée par l'activité n'a pas été réparti, `revoked` pour une attribution retirée
tant que l'événement de son retrait n'a pas été réparti — la ligne est alors supprimée —, rien sinon. Une attribution
retirée n'est plus détenue. Une table `tilttek_badges_award` existante reçoit la colonne par la prochaine migration de
l'application.
