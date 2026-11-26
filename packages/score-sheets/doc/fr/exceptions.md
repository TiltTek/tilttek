<!-- phil-dev source-sha256=a851920ec1739e535e8dc73dc3a12ba8a16e0d8d99e7ad4e07789a4c3e483e26 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\ScoreSheets\Exception\ScoreSheetsException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidSheet` | Une déclaration ne tient pas ensemble, ou ne se décode pas : une clé, un libellé ou une borne refusé, une catégorie qui en lit une déclarée après elle, une opération hors de ses bornes, un JSON trop long ou brisé | `The category "fleet" reads "boats", which is not declared before it.` |
| `InvalidEntry` | Une valeur, un participant, une équipe ou un identifiant est refusé, ou un résultat est trop grand pour être calculé | `The category "boats" takes a value between 0 and 12.` |
| `IllegalTransition` | Le statut de la feuille refuse le changement | `A closed sheet takes no score: reopen it first.` |
| `UnknownMatch` | Le magasin ne contient aucune feuille pour cette partie | `The store holds no sheet for the match "m-2031".` |
| `ConcurrentChange` | La feuille a changé depuis la version sur laquelle le changement a été fait | `The sheet of the match "m-2031" is at version 4, not 3.` |
