<!-- phil-dev source-sha256=55368fa63cd3e497ccbc454aded5e56b9c58781ba6746f2f4b4f6476a919dfaa -->
# Points d'extension

| Point | Ce que donne une application | Où |
|---|---|---|
| `Reservation\ReservationStore`, `Trade\OfferStore`, `Transfer\TransferLog` | Le stockage des réservations, des offres et des transferts, à la place des stockages en mémoire | [Écrire un stockage](writing-a-store.md) |
| `Transaction` | L'unité qui exécute ensemble les étapes d'un échange, comme une transaction de base de données | [Écrire un stockage](writing-a-store.md) |
| `Trade\Inventory` | Les objets des membres tels que les collections de l'application les gardent : à qui appartient un objet, s'il peut changer de mains, les déplacements d'un échange | [Échanges](trades.md) |
| `Reward\Catalog`, `Reward\WritableCatalog` | Les récompenses offertes contre des points, tirées d'une configuration ou d'un catalogue que le site modifie | [Récompenses](rewards.md#le-catalogue) |
| `Reward\Requirements` | Les conditions des récompenses (`badge:helper`, `level:5`), auxquelles répondent les autres paquets de l'application | [Récompenses](rewards.md#avant-de-réserver) |
| `MemberSince` | Quand un membre a adhéré, pour le délai qu'attend un nouveau compte | [Gardes](guards.md) |
| `Announcer` | Ce qui est fait de chaque `Event\ExchangeEvent` : une récompense livrée, un membre informé | [Événements](events.md) |
| `Psr\Clock\ClockInterface` | L'horloge que lisent les services : celle du système en production, une horloge fixe dans les tests | [Tests](testing.md) |

Chaque point est passé au constructeur du service qui l'utilise ; les services ne gardent aucun état propre entre deux
appels.
