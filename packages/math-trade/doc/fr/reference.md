<!-- phil-dev source-sha256=9a050aff6b60fa36ea14dd4d4e8d286edadd120e58200522597de651a7c62980 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `TradeList` | L'échange entier : participants, offres et souhaits, vérifiés les uns contre les autres et contre les limites |
| `Participant` | Quelqu'un qui participe, et les offres qu'il fait |
| `Offer` | Un exemplaire d'un jeu mis dans l'échange, son jeu, et ses souhaits, du plus souhaité au moins souhaité |
| `Contract\Wish` | Une ligne d'une liste de souhaits : les offres qu'elle nomme |
| `Want` | Un souhait pour une seule offre |
| `WantGroup` | Un souhait pour n'importe laquelle de plusieurs offres, à une seule priorité |
| `Limits` | La taille que peuvent avoir une liste et son document JSON |
| `TradeSolver` | Trouve le plus d'échanges, aux meilleures priorités, les égalités étant départagées par le germe |
| `TradeResult` | Les échanges trouvés, le germe, et les offres gardées |
| `TradeCycle` | Une boucle d'échanges |
| `Trade` | Une offre qui change de mains : offre, donneur, receveur |
| `Swap` | Ce qu'une offre échangée signifie pour son propriétaire : ce qu'il envoie et à qui, et ce qu'il reçoit et de qui |
| `TradeListJson` | Écrit une liste d'échange en JSON, et la relit |
| `Exception\MathTradeException` et ses trois exceptions | [Exceptions](exceptions.md) |

L'espace de noms `Internal` est celui de la bibliothèque : ses classes peuvent changer dans n'importe quelle version.
