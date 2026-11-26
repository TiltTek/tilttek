<!-- phil-dev source-sha256=c1a789647413836c6f98eca84795560ff26615393186d2405cb9924394b3347c -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `SheetDefinition` | La feuille d'un jeu : catégories, bris d'égalité et équipes, lue depuis JSON et réécrite |
| `Category` | Une ligne d'une feuille : saisie entre ses bornes, ou calculée |
| `Tiebreaker`, `Direction` | Un bris d'égalité, et quelle valeur gagne |
| `TeamCombine` | Comment les totaux des membres font le score d'une équipe : somme ou meilleur |
| `Comparison` | La condition d'une prime |
| `Operation\Operation` | Comment une catégorie calculée obtient sa valeur |
| `Operation\Sum`, `Product`, `Majority`, `Bonus` | Les opérations |
| `Operation\Operations` | Relit les opérations écrites en tableaux |
| `ParticipantId`, `TeamId`, `MatchId` | Des identifiants opaques |
| `Team` | Une équipe et ses membres |
| `Entries` | Les participants, les équipes et les valeurs saisies |
| `Scoring\Scorer` | Calcule un tableau des scores à partir d'une feuille et de ses saisies |
| `Scoring\Scoreboard`, `ParticipantScore`, `TeamScore`, `Step` | Les scores, les rangs et les explications |
| `SheetStatus`, `MatchSheet` | Le statut et la feuille enregistrée d'une partie |
| `ScoreKeeper` | Prépare, publie, remplit, ferme et rouvre les feuilles d'un magasin |
| `MatchResult`, `Placement` | Le résultat neutre d'une partie fermée |
| `Message\MatchResultRecorded` | Le message d'un résultat enregistré, avec des identifiants seulement |
| `BlankSheet` | La feuille vierge à imprimer, en données neutres |
| `Contract\MatchSheetStore` | Le port du magasin |
| `Store\InMemoryMatchSheetStore` | Le magasin en mémoire, pour les tests |
| `Exception\ScoreSheetsException` et ses cinq exceptions | [Exceptions](exceptions.md) |
