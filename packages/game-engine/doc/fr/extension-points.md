<!-- phil-dev source-sha256=56832a300bd419eb7bfa32ac08b5bd867b2ece69561755491e405f9ea86833b0 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application fournit | Où |
|---|---|---|
| `Contract\Game` | Un jeu : sa définition, sa mise en place, ses règles, les phases entrées, sa fin et ses vues | [Écrire un jeu](writing-a-game.md) |
| `Contract\LogStore` | Le stockage des journaux, la vérité de chaque table | [Conserver et publier](storing-and-publishing.md#le-magasin-de-journaux) |
| `Contract\StateCache` | Un cache des états courants | [Conserver et publier](storing-and-publishing.md#le-cache-détats) |
| `Contract\TablePublisher` | Comment les joueurs apprennent qu'une table a changé : Mercure, un WebSocket | [Conserver et publier](storing-and-publishing.md#la-publication) |

Chaque point est passé au constructeur de `Tables` ou de `Games` ; le moteur et `Tables` ne gardent aucun état propre
entre deux appels.
