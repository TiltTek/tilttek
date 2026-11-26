<!-- phil-dev source-sha256=9cf1f13bbd767043d67596ff88630baab857c6b6b7b1c72b5ce04bc1032c26fa -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Où |
|---|---|---|
| `SheetDefinition` | Les feuilles de ses jeux, déclarées en données | [Déclarer une feuille](declaring-sheets.md) |
| `Contract\MatchSheetStore` | Le magasin de ses feuilles de partie, sur sa propre base de données | [Feuilles de partie](match-sheets.md#un-magasin-à-vous) |
| `Message\MatchResultRecorded` | Le *handler* qui transmet un résultat à son journal de jeu ou à son classement | [Transmettre les résultats](results.md) |
| La mise en page d'une feuille | Le HTML ou le PDF d'une `BlankSheet` ou d'un `Scoreboard` | [Imprimer une feuille vierge](printing.md) |

Les déclarations, les saisies, les tableaux des scores et les résultats sont des valeurs ; les opérations forment un
ensemble fermé que le codec lit. `ScoreKeeper` et `Scorer` ne gardent aucun état entre deux appels.
