<!-- phil-dev source-sha256=54c822e49731290680492a93bf4c242f8f69da8420dd4436b6d1e34f01b290cf -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Où |
|---|---|---|
| `Contract\BingoStore` | Là où les jeux et leurs cartons sont gardés, à une version | [Stockage](storing.md) |
| Le secret de `CardDealer` | Le secret du serveur qui tire chaque carton et chaque code de vérification | [Les cartons et leur germe](cards-and-seeds.md) |
| `WinPattern::custom()` | Des motifs bien à elle | [Mener un jeu](running-a-game.md#la-réserve-la-grille-et-les-motifs) |
| `Trigger` | Le geste du journal d'activité qui coche chaque case | [Mener un jeu](running-a-game.md#cocher-par-lactivité) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Qui apprend chaque case cochée, chaque appel et chaque victoire | [Célébrations](celebrations.md#les-événements) |
| `Psr\Clock\ClockInterface` | Le moment de chaque victoire | [Installation](installation.md#brancher-la-bibliothèque) |
| La variable `labels` et le bloc `won` | Les mots du carton, et sa célébration | [Célébrations](celebrations.md#le-gabarit-du-carton) |
| La variable `labels` et le bloc `footer` du lot imprimé | Les mots des cartons imprimés | [Cartons imprimés](printing.md#le-gabarit) |
| `Symfony\Component\Mercure\HubInterface` et un préfixe de sujet | Là où les gestes d'un jeu sont poussés | [Mises à jour en direct](live-updates.md) |

Chaque point est passé à un constructeur ; aucun service de la bibliothèque ne garde d'état entre deux appels.
