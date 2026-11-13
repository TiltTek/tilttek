<!-- phil-dev source-sha256=5ca5ece15236b580be1ea30f1f9d3dd7140e0a15062ef9b795b82100dd00cedd -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application donne | Où |
|---|---|---|
| `Contract\SecretRollStore` | Le stockage des journaux et des jets scellés | [Stocker et publier](storing-and-publishing.md#le-dépôt-de-stockage) |
| `Contract\JournalPublisher` | La façon dont la table apprend chaque entrée : Mercure, un WebSocket, un pont de clavardage | [Stocker et publier](storing-and-publishing.md#publier) |
| `Tilttek\Dice\DiceRoller` | La notation, les dialectes et les limites avec lesquels les jets sont lus et vérifiés | [Installation](installation.md#brancher-la-bibliothèque) |
| `Random\Randomizer` | La source des germes ; la source sécurisée du système par défaut | [Installation](installation.md#brancher-la-bibliothèque) |

Chaque point est passé au constructeur de `SecretRolls` ou de `RollVerifier` ; ni l'un ni l'autre ne garde d'état entre
deux appels.
