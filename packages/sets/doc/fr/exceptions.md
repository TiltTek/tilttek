<!-- phil-dev source-sha256=669f2475c0af63ed8c33e3d11ce7ba899f747f61b10032b8391356063b679497 -->
# Exceptions

Toute exception de la bibliothèque implémente `Tilttek\Sets\Exception\SetsException`.

| Exception | Lancée quand | Message, par exemple |
|---|---|---|
| `InvalidSet` | Un code, un membre, une version, un palier, une récompense, des possessions ou un détenteur est refusé, ou une définition ne se lit pas | `The tier "gold" requires 30 members; the set "dice" has 20.` |
| `UnknownSet` | Un ensemble, ou une version de celui-ci, n'a jamais été défini | `The set "dice" has no version 4.` |
| `DefinitionConflict` | Une version est définie de nouveau avec un autre contenu, ou une version est plus ancienne que la dernière | `The set "dice" already has a version 1 with other content: a changed set takes a new version.` |

`InvalidSet` étend `InvalidArgumentException` ; les autres étendent `RuntimeException`.
