<!-- phil-dev source-sha256=7d09e7eb5982f7cf1d7b26eac923faac006a9ffe550de95b22ce77902735d339 -->
# Référence

| Type | Rôle |
|---|---|
| `ExchangeKey` | La clé qui fait qu'un échange n'est écrit qu'une fois |
| `Refusal` | Pourquoi un échange est refusé |
| `Reward\Reward` | Une récompense : prix, devise, stock, disponibilité, limite, conditions |
| `Reward\Catalog`, `Reward\WritableCatalog` | Les récompenses d'un site ; un catalogue que l'application modifie |
| `Reward\InMemoryCatalog` | Le catalogue en mémoire, à partir de récompenses ou d'une configuration |
| `Reward\Requirements`, `Reward\NoRequirements` | Les conditions des récompenses, vérifiées par l'application ; aucune remplie |
| `Reservation\Reservation`, `Reservation\ReservationStatus` | Une réservation et où elle en est |
| `Reservation\ReservationStore`, `Reservation\InMemoryReservationStore` | Où sont gardées les réservations |
| `Reservation\Redemptions` | Réserver, confirmer, annuler, faire expirer ; refus et unités restantes |
| `Transfer\Transfer` | Des points qu'un membre a donnés à un autre |
| `Transfer\TransferLog`, `Transfer\InMemoryTransferLog` | Le journal des transferts |
| `Transfer\Transfers` | Donner des points dans les limites des gardes ; le journal |
| `Trade\Offer`, `Trade\OfferStatus` | Une offre d'échange et où elle en est |
| `Trade\ItemMove` | Un objet qui change de mains |
| `Trade\Inventory`, `Trade\InMemoryInventory` | Les objets des membres, tels que l'application les garde |
| `Trade\OfferStore`, `Trade\InMemoryOfferStore` | Où sont gardées les offres et leurs retenues |
| `Trade\Trades` | Offrir, accepter, refuser, retirer, contrer, faire expirer |
| `Allowance` | Combien un membre peut donner sur une période |
| `Guards` | Échange avec soi-même, nouveaux comptes, allocation |
| `MemberSince`, `NoMemberSince` | Quand un membre a adhéré, selon l'application ; personne de connu |
| `Transaction`, `ImmediateTransaction` | Les étapes d'un échange comme une seule unité ; exécutées à mesure |
| `Announcer`, `SilentAnnouncer` | Informé de chaque événement ; n'informe personne |
| `Event\ExchangeEvent` et ses onze événements | [Événements](events.md) |
| `Erasure`, `ExchangeErasure` | Ce qu'a fait l'effacement d'un membre ; l'effacement |
| `Exception\ExchangeException` et ses six exceptions | [Exceptions](exceptions.md) |
