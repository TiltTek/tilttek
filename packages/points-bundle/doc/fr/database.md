<!-- phil-dev source-sha256=507d9a0884d1ce86574a9098a60e34f9be9a873c54071badd6230cbcd6c15ab7 -->
# Le registre en base de données

## Les tables

| Table | Colonnes | Index |
|---|---|---|
| `tilttek_points_movement` | `position` (auto-incrément), `movement_key` (400), `kind` (16), `currency` (32), `debit` et `credit` (340), `amount` (bigint), `occurred_at`, `recorded_at`, `cause` (255, null), `reverses` (400, null) | unique `movement_key` ; `debit, currency` ; `credit, currency` ; `cause` ; `kind, occurred_at` |
| `tilttek_points_lock` | `account` (340), `currency` (32), `payments` (bigint) | clé primaire sur `account, currency` |

Le registre n'a pas de colonne de solde : un solde est la somme des mouvements d'un compte, calculée en SQL. Une ligne
de verrou ne contient pas non plus de montant : elle compte les paiements faits à partir du compte, et existe pour que
chaque paiement puisse la verrouiller.

## Écrit une seule fois

`DbalLedger` cherche d'abord la clé. Si elle n'existe pas, il insère le mouvement par une instruction qui n'écrit rien
si un autre processus a écrit la même clé entre-temps, sans interrompre la transaction qui l'entoure :

| Plateforme | Insertion |
|---|---|
| PostgreSQL | `INSERT … ON CONFLICT (movement_key) DO NOTHING` |
| MySQL, MariaDB | `INSERT IGNORE …` |
| SQLite | `INSERT OR IGNORE …` |
| Autres | `INSERT …`, dont la violation d'unicité dans une telle course parvient à l'appelant |

Le mouvement est ensuite relu : l'appelant obtient la ligne que la base a gardée, et `MovementConflict` quand cette
ligne est un autre mouvement.

## Paiements

Une dépense ou un transfert s'exécute dans une transaction qui insère la ligne de verrou du payeur si elle manque, puis
la met à jour. La mise à jour tient le verrou en écriture de la ligne sur PostgreSQL, MySQL, MariaDB et SQL Server, et
le verrou de toute la base sur SQLite, jusqu'à la fin de la transaction. Ce n'est qu'ensuite que la transaction cherche
la clé du mouvement, calcule le solde et n'écrit le mouvement que si le solde le couvre. Deux paiements à partir du même
compte au même moment sont vérifiés l'un après l'autre, et le second lit les mouvements que le premier a validés ; une
ligne de verrou qui ne peut pas être mise à jour arrête le paiement avec une `LogicException`.

Sur MySQL et MariaDB, dont l'isolation par défaut, REPEATABLE READ, tient les lectures d'une transaction à l'instantané
de sa première lecture, la transaction du paiement s'exécute en READ COMMITTED. Un paiement fait dans une transaction
que l'application a déjà ouverte ne peut pas en changer l'isolation : il lit le mouvement et le solde avec
`LOCK IN SHARE MODE`, qui lit les dernières lignes validées, quoi que la transaction ait lu avant.

## Dates

Les moments sont écrits en UTC, à la seconde, et relus en UTC. Les bornes de la période d'un plafond sont aussi
comparées en UTC, quel que soit le fuseau horaire dans lequel elles ont été calculées.

## Lignes illisibles

Une ligne dont le montant ou la position n'est pas un entier, dont une colonne de texte ne contient pas de texte, ou
dont la bibliothèque refuse les valeurs (un genre inconnu, un montant négatif) lève une exception plutôt que d'être lue
comme autre chose.
