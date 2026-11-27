<!-- phil-dev source-sha256=371066e9ffe600f3689fe84b26b1b013665486ef06083bf504f9e50f2fce68db -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/math-trade` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Participant`, `Offer`, `Want` et `WantGroup` : la liste d'échange immuable — les participants, leurs offres, le jeu
  qu'est chaque exemplaire, et les souhaits du plus souhaité au moins souhaité, un groupe étant n'importe laquelle de
  plusieurs offres à une même priorité —, avec des identifiants de 1 à 100 octets d'UTF-8 sans caractère de contrôle.
- `TradeList` : la liste vérifiée dans son ensemble — des participants et des offres uniques, des souhaits pour des
  offres existantes d'autres participants, aucune offre souhaitée deux fois — et contre les `Limits` : participants,
  offres, offres par participant, souhaits par offre, taille d'un groupe, offres souhaitées et longueur d'un document
  JSON.
- `TradeSolver` : le plus d'offres échangées, puis la meilleure somme des priorités, sous forme d'une couverture par
  cycles de coût minimal trouvée par une affectation creuse de coût minimal ; un participant qui souhaite un jeu à
  partir de plusieurs offres, avec plusieurs exemplaires souhaités, le reçoit au plus une fois ; un germe d'au plus 256
  octets départage les égalités, pour que la même liste et le même germe donnent le même résultat quel que soit l'ordre
  de la liste.
- `TradeResult`, `TradeCycle`, `Trade` et `Swap` : les boucles d'échanges, chaque échange, ce que chaque participant
  envoie à qui et reçoit de qui, et les offres gardées.
- `TradeListJson` : les listes écrites et lues en JSON, au format documenté `tilttek/math-trade`, version 1, avec un
  lecteur strict borné par la longueur du document.
- `Exception\MathTradeException`, avec `InvalidTradeList`, `LimitExceeded` et `InvalidSeed`.
- Un banc d'essai de 1 000 offres, et une cible de fuzzing pour le lecteur JSON.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
