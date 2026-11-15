<!-- phil-dev source-sha256=1ecb0d5ba80fd1c3cf20dbfa358390f8014a53b3ca8c5b4eaf144eadc0a175e5 -->
# Base de données

| Table | Clé | Contient |
|---|---|---|
| `tilttek_leaderboards_contribution` | classement, activité | Ce que chaque activité a apporté à chaque classement et à ses critères de départage, dans quelle période, pour quel membre ; c'est la clé qui fait qu'une activité ne compte qu'une fois |
| `tilttek_leaderboards_score` | classement, période, membre | Le score et les critères de départage de chaque membre dans chaque classement et période, et quand il a été atteint ; indexée sur le classement, la période, le score, les critères de départage et le moment, l'ordre des rangs |
| `tilttek_leaderboards_snapshot` | classement, période | Les classements figés à la fin d'une période : quand, et combien de membres étaient classés |
| `tilttek_leaderboards_placing` | classement, période, membre | Les lignes des membres dans chaque instantané : rang, score, moment |
| `tilttek_leaderboards_appearance` | membre | Comment paraît chaque membre qui a choisi de paraître : `named` ou `pseudonym`, avec le pseudonyme |
| `tilttek_leaderboards_league_tier` | classement, membre | Le palier que chaque membre a atteint dans chaque classement doté d'une ligue |
| `tilttek_leaderboards_league_membership` | classement, semaine, membre | Le palier, la division et la cohorte de chaque membre dans chaque semaine où il a pris part, quand il est arrivé et où il est allé à la fin ; indexée sur le classement, la semaine, le palier et la division ; la clé place un membre une fois par semaine |
| `tilttek_leaderboards_league_week` | classement, semaine | Les semaines réglées, et quand ; la clé règle une semaine une seule fois |
| `tilttek_leaderboards_lead` | classement, période, membre | Les premières places prises à mesure que les scores changent, avec `celebrate_on: score_change`, et quand ; indexée sur le membre ; la clé dit une seule fois à un membre, par classement et par période, qu'il a pris la tête |

La référence d'un membre compte 320 octets au plus (un genre de 64, un deux-points et un identifiant de 255), le code
d'une période 64, et la clé d'un classement 96 dans les tables des contributions, des scores, des instantanés, des
lignes d'instantané et des premières places : son nom de 64 ou, pour un classement par objet, son nom, `@` et une empreinte de 24. Les critères
de départage occupent deux colonnes, `tie_break_1` et `tie_break_2`, et `tie_breaks` dit combien une ligne en contient.
Les moments (`occurred_at`, `reached_at`, `taken_at`, `joined_at`, `settled_at`) sont des colonnes du type
`datetime_utc_immutable` : écrits en UTC, à la seconde, et relus en UTC, quels que soient le fuseau du moment donné et
le fuseau par défaut de PHP. La colonne `placing` est entre guillemets dans chaque instruction, un mot réservé de
PostgreSQL.

Avec `valkey` activé, les contributions et les scores sont gardés dans Valkey à la place des deux premières tables, dans
les ensembles triés que décrit la bibliothèque ; les autres tables restent. Les commandes envoyées sont communes : `EVAL`
de courts scripts qui appellent `HSET`, `HSETNX`, `HGET`, `HDEL`, `SADD`, `SREM`, `ZADD` et `ZREM`, et `HGET`, `HMGET`,
`SMEMBERS`, `ZRANGE`, `ZLEXCOUNT` et `ZCARD`, par `rawCommand()` du client ou `executeRaw()` de Predis, de sorte que
l'extension redis, Relay, Predis et `ValkeyGlide` de valkey-glide-php les servent également. Rien ne demande au serveur
de persister : ni `SAVE`, ni `BGSAVE`, ni `CONFIG SET` n'est envoyé, et la persistance propre du serveur (instantanés
RDB, AOF) s'applique telle que configurée, ou pas du tout sur un serveur géré qui l'interdit ou dans les tests. Un
serveur qui perd ses données perd les scores, jamais les instantanés, les choix, les ligues et les premières places gardés dans `store` ;
`tilttek:activity:rebuild leaderboards` reconstruit ceux que mesure le journal, tandis que les valeurs qu'une
application rapporte sont rapportées de nouveau par elle.

Une contribution, un instantané, une appartenance et une semaine réglée sont insérés par une instruction qui n'écrit
rien quand la ligne existe : `ON CONFLICT DO NOTHING` sur PostgreSQL, `INSERT IGNORE` sur MySQL et MariaDB, `INSERT OR IGNORE` sur SQLite. Ainsi, deux processus qui
remettent la même activité ne la comptent qu'une fois sans interrompre leurs transactions. Un score est remplacé
(supprimé puis inséré) dans une seule transaction ; un score rapporté est inséré de la même façon quand le membre n'en
a aucun, ou sinon écrit par un `UPDATE` dont la condition est la comparaison (`reached_at <= ?` et d'autres valeurs
pour `latest`, une meilleure valeur ou un meilleur critère de départage pour `highest`), si bien que de deux rapports
au même moment, un plus ancien ou plus bas ne remplace jamais l'autre ; les moments sont gardés à la seconde, et deux
rapports `latest` dans la même seconde comptent comme atteints ensemble ; les lignes d'un instantané sont insérées dans la transaction de
l'instantané, et le choix d'un membre est remplacé de la même façon. Une semaine se règle dans une seule transaction
qui commence par insérer sa ligne : un second règlement n'insère rien et n'écrit rien d'autre. Deux membres qui entrent
dans une division au même moment peuvent se partager sa dernière place, qui en compte alors un de plus : une division
compte *environ* sa taille.

Ces instructions s'exécutent sur SQLite, MySQL 8.4 et PostgreSQL 17 dans les tests du bundle ; MariaDB suit le chemin de
MySQL.
