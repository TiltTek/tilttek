<!-- phil-dev source-sha256=ee80a5816a81b0a0fdc65058b1529f324de0891736d8b5f13d8e486f5b28722a -->
# Référence

| Type | Rôle |
|---|---|
| `Bingo` | Crée les jeux, distribue et imprime les cartons, appelle les cases, enregistre et annule les gestes, reçoit les réclamations, vérifie par numéro de série ou par étiquette et ferme |
| `BingoGame` | Un jeu : réserve, grille, motifs, mode, cartons distribués, cases appelées et gagnants, à une version |
| `Card` | Un carton, celui d'un joueur ou un carton imprimé : numéro de série, disposition, cases cochées et victoire, à une version |
| `CardDealer` | Tire une disposition et une étiquette du secret du serveur ; l'empreinte d'une disposition |
| `SerialLabel` | L'étiquette d'un carton imprimé : son numéro de série et son code de vérification, lue comme saisie |
| `Claim` | Ce que le serveur a trouvé sur un carton : les cellules cochées et le motif gagnant |
| `TickMode` | `Activity` ou `Called` |
| `Square`, `SquarePool` | Une case, et la réserve où les cartons sont tirés |
| `Trigger` | Le geste qui coche une case |
| `Grid` | La taille d'un carton et sa case libre du centre |
| `WinPattern`, `WinPatterns` | Une façon de gagner, et les façons d'un jeu dans l'ordre |
| `Activity\BingoActivityObserver` | Coche et décoche les cartons d'après le journal d'activité |
| `Contract\BingoStore` | Là où les jeux et les cartons sont gardés |
| `Store\InMemoryBingoStore` | Le dépôt en mémoire |
| `Event\BingoWon`, `SquareTicked`, `SquareCalled` | Voir [Célébrations](celebrations.md#les-événements) |
| `View\CardView`, `View\CellView` | Ce que montre le gabarit du carton |
| `View\PrintedCardView` | Ce que le gabarit d'un lot imprimé montre de chaque carton |
| `Mercure\BingoPublisher` | Pousse les appels, les victoires et les cases cochées par Mercure |
| `Exception\BingoException`, `InvalidBingo`, `BingoRefused`, `BingoConflict` | Voir [Exceptions](exceptions.md) |

`Identifier` est interne.

Le gabarit `templates/card.html.twig` et le contrôleur Stimulus `card`, dans `assets/`, sont décrits dans
[Célébrations](celebrations.md) ; le gabarit `templates/print.html.twig`, dans [Cartons imprimés](printing.md).
