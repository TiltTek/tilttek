<!-- phil-dev source-sha256=4296fde1d7a4a3a43e6fc5a153df13697eb870b866473a1a6db38dfdcddaaa04 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\LootTables\Exception\LootException` : attrapez-la pour les
attraper toutes.

| Exception | Étend | Levée quand |
|---|---|---|
| `InvalidLoot` | `InvalidArgumentException` | Une entrée, une table, une boîte, un catalogue, les faces d'un résultat ou un tirage en attente est refusé |
| `UnknownLoot` | `OutOfBoundsException` | Un catalogue ne contient aucune boîte ou aucune table de cette clé |
| `InvalidCode` | `InvalidArgumentException` | Un code de boutique ou un texte de tirage ne se lit pas comme les dés de sa boîte, ou la boîte n'a pas de code de boutique |

`LootDrawer::verify()` ne lève rien : une preuve illisible donne faux.
