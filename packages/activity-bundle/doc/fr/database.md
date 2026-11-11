<!-- phil-dev source-sha256=b83a9b8939dc324d87b1c8bb7a5d589541cf7460fd26999aedecc48c3bcdcae8 -->
# Le journal dans la base de données

## Les tables

`tilttek_activity_entry`, une ligne par entrée :

| Colonne | Type | Contient |
|---|---|---|
| `position` | grand entier, auto-incrément, clé primaire | La position, jamais donnée deux fois |
| `kind` | chaîne (16) | `recorded` ou `cancelled` |
| `idempotency_key` | chaîne (255) | La clé ; unique avec `kind` |
| `member` | chaîne (320) | La référence du membre, `kind:identifier` |
| `verb` | chaîne (128) | Le verbe |
| `object` | chaîne (320), peut être nulle | La référence de l'objet |
| `quantity` | entier | La quantité |
| `context` | JSON | Le contexte |
| `occurred_at` | date et heure | Le moment du geste, en UTC |
| `recorded_at` | date et heure | Le moment où l'entrée a été écrite, en UTC |

Index : `tilttek_activity_entry_key` (unique : `idempotency_key`, `kind`), `tilttek_activity_entry_member`,
`tilttek_activity_entry_object`, `tilttek_activity_entry_verb` (`verb`, `occurred_at`).

`tilttek_activity_checkpoint` : `projection` (chaîne 64, clé primaire), `position` (grand entier), `updated_at`.

## Écrire une seule fois

Une entrée est insérée par une instruction qui n'écrit rien quand sa clé et sa sorte sont déjà présentes, sans
interrompre la transaction englobante :

| Plateforme | Instruction |
|---|---|
| PostgreSQL | `INSERT … ON CONFLICT (idempotency_key, kind) DO NOTHING` |
| MySQL, MariaDB | `INSERT IGNORE …` |
| SQLite | `INSERT OR IGNORE …` |
| Autres | Un `INSERT` simple, dont la violation d'unicité compte comme « déjà présente » |

L'entrée est ensuite relue : l'appelant reçoit la ligne gardée par la base de données, et sait si cet appel l'a écrite.
Sur les « autres » plateformes, une violation à l'intérieur d'une transaction peut l'interrompre, selon ce que décide
la plateforme.

## Dates et positions

Les dates sont écrites en UTC, à la seconde près, et relues en UTC. Les positions viennent de l'auto-incrément ;
`AUTOINCREMENT` de SQLite, l'identité de PostgreSQL et InnoDB ne donnent jamais un numéro deux fois. Deux transactions
qui valident dans l'ordre inverse de celui où elles ont pris leurs positions peuvent faire sauter la position la plus
ancienne à un rattrapage exécuté entre elles. Une application qui écrit de l'activité depuis de nombreuses transactions
concurrentes, et qui a besoin que chaque entrée soit projetée, lance une reconstruction de la période quand elle
soupçonne un trou, ou fait ses rattrapages depuis un seul rédacteur.

## Lire

Les filtres sont écrits en SQL : le membre, l'objet et la période comparent des colonnes. Un motif de verbe avec un
préfixe compare le début du verbe par le `SUBSTRING` de la plateforme, qu'un trait de soulignement dans un verbe ne peut
pas tromper comme il le ferait avec un `LIKE`. Une ligne dont le contexte ou les colonnes ne se lisent pas — écrite à la
main, ou par une version plus ancienne — lève `UnexpectedValueException` avec la colonne en faute.
