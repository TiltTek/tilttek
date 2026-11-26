<!-- phil-dev source-sha256=a281375320a0def62445b97daee7009fce73e3ae1d0fa1bafa591c465b88ca3f -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce qu'une application donne | Où |
|---|---|---|
| `Contract\GameStore` | Le magasin de ses parties, sur sa propre base de données | [Parties et magasins](games-and-stores.md#un-magasin-à-vous) |
| `Deck` | Les cartes de ses jeux, déclarées en données | [Cartes et zones](cards-and-zones.md#paquets) |
| Les règles du jeu | À qui le tour et quel joueur peut jouer quel geste, vérifié avant `CardTable::play()` | [Gestes](gestures.md) |
| La poussée des vues | La vue propre à chaque joueur, envoyée après chaque geste | [Vues des joueurs](player-views.md) |

Les cartes, les zones, les gestes et les vues sont des valeurs ; les gestes forment un ensemble fermé que le codec lit.
`CardTable` ne garde aucun état entre deux appels.
