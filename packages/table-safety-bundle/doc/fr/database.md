<!-- phil-dev source-sha256=6c9543c6966b6c8dd5dea62a6644a738da357d25ac38937cfadc948b16095921 -->
# Base de données

Le bundle ajoute quatre tables au schéma que l'ORM génère pour sa connexion, par `TableSafetySchemaListener` :

| Table | Clé | Contient |
|---|---|---|
| `tilttek_table_safety_table` | `table_ref` | les réglages de chaque table, en JSON |
| `tilttek_table_safety_participation` | `table_ref`, `participant` | les lignes et voiles, la liste et la séance zéro de chaque participant, en JSON, le moment de leur première réponse et celui de leur dernier changement |
| `tilttek_table_safety_signal` | `id` | les signaux : table, genre, auditoire, le moment et sa microseconde, l'auteur d'un signal nominatif, le moment où il a été vu |
| `tilttek_table_safety_feedback` | `table_ref`, `session`, `participant` | les étoiles et souhaits de chaque participant pour chaque séance, en JSON |

Les participations et les étoiles et souhaits sont indexés par participant, pour un export ou un effacement ; les
signaux par table et microseconde, pour les derniers d'une table, et par auteur. L'auteur d'un signal anonyme est null.
Les moments sont écrits en UTC, à la seconde près ; la microseconde garde l'ordre de deux signaux de la même seconde.
