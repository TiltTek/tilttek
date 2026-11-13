<!-- phil-dev source-sha256=79c5665c14d91cb665c996820ca36fed434726179c93e4e5cfe428d058c96e15 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\BingoException`.

| Exception | Étend | Lancée quand |
|---|---|---|
| `InvalidBingo` | `InvalidArgumentException` | Une valeur sort de ses bornes : un identifiant, un libellé, une réserve, une grille, une cellule, un motif, un numéro de série, un code de vérification, la taille d'un lot imprimé, un secret trop court, un préfixe de sujet |
| `BingoRefused` | `DomainException` | Les règles refusent un geste : un jeu fermé, un appel dans un jeu coché par l'activité, une réclamation sur le carton d'un autre joueur, un carton qui ne correspond pas à son germe ou une étiquette qui n'est pas la sienne, l'impression dans un jeu coché par l'activité, un jeu ou un carton inconnu, aucun carton différent des autres |
| `BingoConflict` | `RuntimeException` | Un jeu ou un carton a changé dans le dépôt depuis sa lecture, ou son identifiant, son numéro de série ou son joueur est pris |
