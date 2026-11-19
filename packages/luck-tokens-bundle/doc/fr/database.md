<!-- phil-dev source-sha256=1816e754007e4337d2efb74cc609c703bcc2f47eba23bac94151c21c794e1058 -->
# Les registres dans la base de données

## Les tables

| Table | Contient |
|---|---|
| `tilttek_luck_tokens_entry` | Une ligne par entrée de chaque registre : la table et la position — la clé primaire —, l'opération, le type, la sorte, le détenteur, le changement (`delta`), le solde, l'acteur, l'heure, la raison et la session |
| `tilttek_luck_tokens_rules` | Les règles de chaque table en JSON, avec `rules: doctrine` |

`LuckTokensSchemaListener` les ajoute au schéma que l'ORM génère pour la connexion des jetons, la table des règles
seulement avec `rules: doctrine`, de sorte que `doctrine:migrations:diff` les écrit et que `doctrine:schema:validate` ne
demande pas de les supprimer. Sans l'ORM, `LuckTokensTables::tables($platform)` les donne pour une migration à vous.

| Index | Colonnes | Répond à |
|---|---|---|
| Clé primaire | `table_ref`, `position` | La dernière position, le registre dans l'ordre, deux écrivains à la même position |
| `…_balance` | `table_ref`, `token_kind`, `holder`, `position` | Un solde : la dernière entrée du détenteur pour la sorte |
| `…_operation` | `table_ref`, `operation_id`, `position` | Les entrées d'une opération envoyée de nouveau, dans l'ordre |
| `…_session` | `table_ref`, `session_key` | Si une session a commencé |
| `…_holder`, `…_actor` | `holder` ; `actor` | L'effacement d'un membre, dans chaque table |

Chaque entrée porte le solde qu'elle laisse : un solde est une ligne lue par son index, quelle que soit la longueur du
registre. Les références font 320 caractères, les identifiants 64 ; les heures sont le texte de la bibliothèque en UTC,
à la microseconde, `2026-11-13T20:15:03.250000Z`, qui se trie comme il se lit. Sur MySQL et MariaDB, les colonnes de
texte prennent la collation `utf8mb4_bin`, de sorte que `member:Ana` et `member:ana` sont deux détenteurs, comme ils le
sont en PHP ; SQLite et PostgreSQL comparent déjà octet par octet.

## Les écrivains simultanés

`LuckTokens` lit la dernière position, vérifie l'opération selon les soldes, et demande au stockage d'ajouter ses
entrées juste après cette position. `DbalTokenLedger::append()` ouvre une transaction, relit la dernière position, puis
insère les entrées :

- une autre position lue là est un `LedgerConflict`, et rien n'est écrit ;
- une insertion qui rencontre une entrée de la même table et de la même position — un écrivain qui a validé entre-temps
  — échoue sur la clé primaire ; un blocage mutuel ou une attente de verrou qui expire échoue aussi ; chacun annule la
  transaction et devient un `LedgerConflict`.

La bibliothèque relit alors le registre — la même opération faite entre-temps par une autre requête est une reprise —
et vérifie de nouveau, trois fois en tout. Les tests exécutent quatre processus qui octroient au même détenteur en même
temps, sur SQLite, MySQL et PostgreSQL : chaque octroi est écrit une fois, à des positions qui se suivent. Une table où
beaucoup de joueurs dépensent au même moment peut aussi prendre un verrou, voir [Verrous](locks.md).

## Les règles gardées dans la base de données

Avec `rules: doctrine`, `DbalTokenRules` garde une ligne par table. Le formulaire des réglages d'une table enregistre ses
règles :

```php
use Tilttek\LuckTokens\TableRules;
use Tilttek\LuckTokensBundle\Infrastructure\Doctrine\DbalTokenRules;

$rules->save(TableRules::fromJson($table, $json));   // in place of those it had
$rules->remove($table);                             // the table hands out no tokens; its ledger stays
```

Chaque lecture vérifie de nouveau les règles, de sorte qu'une ligne écrite à la main est refusée avec `InvalidTokenRule`
plutôt qu'utilisée.
