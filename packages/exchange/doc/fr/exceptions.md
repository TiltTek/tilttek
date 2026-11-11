<!-- phil-dev source-sha256=50f5c878513744e9686d3f2c28783adce94762e863b776323cf9738d4119c459 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Exchange\Exception\ExchangeException`. Les exceptions de
`tilttek/points` (`InsufficientPoints` quand un solde ne couvre pas un paiement, `UnknownCurrency`) parviennent à
l'appelant telles quelles.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidExchange` | Une clé, une récompense, une quantité, une réservation, un transfert, une offre, une allocation, une durée ou un fuseau horaire est refusé | `An exchange key is 1 to 200 bytes; this one is 0.` |
| `UnknownReward` | Le catalogue ne contient aucune récompense sous ce code | `The catalogue holds no reward "seat".` |
| `UnknownExchange` | Aucune réservation ni offre n'est écrite sous la clé | `No offer is written under the key "trade:9".` |
| `ExchangeConflict` | Une clé déjà écrite est donnée pour une autre réservation, un autre transfert ou une autre offre | `The key "gift:1" is already written for another transfer.` |
| `ExchangeClosed` | Une réservation ou une offre n'est plus ouverte pour l'opération | `The reservation "order:1" is expired; it cannot be confirmed.` |
| `ExchangeRefused` | Les règles refusent l'échange ; son `refusal` dit pourquoi | `"member:3" has not been a member long enough to exchange with others.` |

## Refus

`ExchangeRefused::$refusal` est un `Refusal`, que l'application explique au membre dans ses propres mots :

| Refus | Sens |
|---|---|
| `OutOfStock` | Chaque unité est réservée ou livrée |
| `NotYetAvailable`, `NoLongerAvailable` | En dehors de la période de la récompense |
| `MemberLimitReached` | Le membre détient autant d'unités qu'un membre peut en avoir |
| `RequirementUnmet` | Une condition de la récompense n'est pas remplie |
| `SelfExchange` | Un membre qui échange avec lui-même |
| `NewMember` | Un compte plus jeune que le délai |
| `AllowanceReached` | L'allocation de la période est atteinte |
| `NotOwned` | Un objet n'appartient pas à celui qui le donne |
| `NotTradable` | Un objet lié à son membre |
| `AlreadyHeld` | Un objet retenu par une autre offre ouverte |
| `NotParty` | Un membre qui agit sur une offre sur laquelle il n'a pas à agir |
