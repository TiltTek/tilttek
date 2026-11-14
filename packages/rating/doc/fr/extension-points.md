<!-- phil-dev source-sha256=ecbef00d0adf8e566c6f38204fae66b9a4e550b97cbae0a6f8bb190b936e892c -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application donne | Où |
|---|---|---|
| `Contract\RatingStore` | Le stockage des cotes des membres | [Stockage](storing.md) |
| `Contract\PlaySource` | Les résultats d'un jeu, du plus ancien au plus récent, pour une relecture | [Rejouer](replaying.md#la-source) |
| `Contract\RatingSystem` | Un autre système de cotes ; `Elo\EloSystem` et `OpenSkill\OpenSkillSystem` sont fournis | [Elo](elo.md), [OpenSkill](openskill.md) |
| `Elo\KFactor` | Un K fixe ou décroissant | [Elo](elo.md#le-facteur-k) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Qui entend parler de chaque changement, par `Event\RatingChanged` et `Event\PeakReached` | [Célébrations](celebrations.md) |

Un système de l'application implémente `Contract\RatingSystem` et `Contract\Rating` : un identifiant à lui, gardé avec
chaque cote, des cotes en nombres entiers et les mêmes nouvelles cotes pour les mêmes entrées. Chaque point est passé à
un constructeur ; aucun service de la bibliothèque ne garde d'état entre deux appels.
