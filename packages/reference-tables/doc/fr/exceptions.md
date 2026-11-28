<!-- phil-dev source-sha256=a1c684dc6ebcec9017e60405f0539d4be22f1df1c59e50111895264d4176b023 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\ReferenceTablesException` : attrapez-la pour toutes les
attraper.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidTable` | `InvalidArgumentException` | Une table, une colonne, une ligne, une cellule ou une plage enfreint une règle ; un modificateur, une valeur entrée, une requête ou une limite sort de ses bornes. |
| `InvalidDocument` | `InvalidArgumentException` | Un document ne peut pas être lu ; le message commence par l'emplacement du champ. |
| `UnknownTable` | `OutOfBoundsException` | Un catalogue ne détient aucune table de cette clé, une table aucune colonne, ou une ligne aucune cellule dans la colonne. |
| `UnknownRow` | `OutOfBoundsException` | Une table ne détient aucune ligne de cette clé, ou aucune plage ne couvre la valeur. |
| `LookupRefused` | `LogicException` | On demande une clé à une table trouvée par plage, une valeur à une table trouvée par clé, on lance une table sans dé, on lit une cellule comme un autre type, ou un dé ne donne aucun nombre. |

Quelques messages :

```text
The table "armor" has no row "chain".
No row of the table "reaction" covers 13.
The table "reaction" is looked up by range, not by key.
The table "weather" has no die to roll.
The rows "1–5" and "5–6" of the table "wandering" overlap.
"high" is not a range such as "12", "3–5", "21+" or "≤0".
tables[0].rows[2].value: a whole number is expected.
```

Un dé ou une cellule lancé au-delà des limites du `DiceRoller` lève la `DiceException` de `tilttek/dice`.
