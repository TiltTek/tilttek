<!-- phil-dev source-sha256=655f1590ac20353ee0d56ae6a7ea9c763848bef570a4cfbf6aa2816d12e283b5 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/exchange` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.1-alpha - 2026-11-20

### Modifié

- `tilttek/points` 0.2 requis : 0.1 n'est plus accepté.

## 0.2.0-alpha - 2026-11-14

### Modifié

- Une réservation est écrite `ReservationStatus::Paying` et devient `Reserved` une fois ses points dépensés, pour
  qu'aucune annulation ni aucune expiration ne la règle entre son écriture et son paiement ;
  `ReservationStatus::isOpen()` reconnaît une réservation en paiement ou réservée, et les unités d'une réservation en
  paiement sont retenues.
- `ReservationStore::settle()` est remplacée par `transition()`, une comparaison-échange d'un statut à un autre ;
  `discard()` supprime une réservation en paiement, et `due()` renvoie les réservations en paiement et réservées qui ont
  dépassé leur durée de vie. Un stockage de l'application implémente `transition()` à la place de `settle()`.
- `Redemptions::confirm()` et `cancel()` lèvent `ReservationPending` pendant que les points sont payés ; un paiement qui
  trouve sa réservation réglée ou disparue rend ses points, et `RewardReserved` est annoncé une seule fois, par l'appel
  qui a rendu la réservation réservée.
- `tilttek/points` 0.2 accepté, tout comme 0.1.

### Corrigé

- Des points pouvaient être dépensés pour une réservation déjà annulée ou expirée, quand l'annulation ou l'expiration
  s'exécutait entre l'écriture de la réservation et la dépense de ses points.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `ExchangeKey` : la clé que l'application donne à une réservation, à un transfert ou à une offre, de 1 à 200 octets,
  qui ne commence jamais par le `#` des clés dérivées du grand livre, pour que la même clé soit un seul échange.
- `Reward`, `Catalog`, `WritableCatalog` et `InMemoryCatalog` : des récompenses avec un prix dans une monnaie de points,
  un stock qui compte chaque unité jamais offerte, une période de disponibilité, une limite par membre et des
  conditions ; `InMemoryCatalog::fromArray()` à partir d'une configuration.
- `Requirements` et `NoRequirements` : les conditions des récompenses, vérifiées par l'application.
- `ReservationStatus`, `Reservation`, `ReservationStore` et `InMemoryReservationStore` : des réservations écrites une
  seule fois par clé, leurs unités vérifiées contre le stock et la limite du membre dans la même étape, réglées une
  seule fois, les dues de la plus ancienne à la plus récente, les réservations d'un membre et leur effacement.
  `InMemoryReservationStore::reset()` vide le stockage, pour un processus de longue durée.
- `Redemptions` : des récompenses réservées en dépensant leurs points sur le grand livre, confirmées une fois livrées,
  annulées ou expirées avec leurs points rendus, les raisons pour lesquelles un membre ne peut pas réserver, les unités
  qui restent, et une réservation demandée de nouveau reprise là où elle s'était arrêtée.
- `Allowance`, `Guards`, `MemberSince` et `NoMemberSince` : aucun échange avec soi-même, un délai pour les nouveaux
  comptes à partir des dates d'adhésion que donne l'application, et une allocation par jour, semaine ISO, mois, année ou
  depuis toujours dans un fuseau horaire.
- `Transfer`, `TransferLog`, `InMemoryTransferLog` et `Transfers` : des points donnés entre membres dans les limites des
  garde-fous, écrits une seule fois par clé dans un journal que l'application examine, avec ce qu'un membre a donné dans
  la période en cours. `InMemoryTransferLog::reset()` vide le journal.
- `ItemMove`, `Inventory`, `InMemoryInventory`, `OfferStatus`, `Offer`, `OfferStore`, `InMemoryOfferStore` et `Trades` :
  des offres d'objets, et des points de chaque côté, dont les objets offerts sont retenus jusqu'à leur clôture ;
  l'acceptation par le destinataire en une seule transaction après vérification de la propriété, de l'échangeabilité,
  des retenues et des soldes ; les refus, les retraits, les contre-offres qui ferment la première offre et ouvrent la
  leur, et l'expiration. `InMemoryInventory::reset()` et `InMemoryOfferStore::reset()` les vident.
- `Transaction` et `ImmediateTransaction` : les étapes d'un échange comme une seule unité.
- `Announcer`, `SilentAnnouncer` et les événements `RewardReserved`, `ReservationConfirmed`, `ReservationCancelled`,
  `ReservationExpired`, `PointsTransferred`, `OfferMade`, `OfferAccepted`, `OfferDeclined`, `OfferWithdrawn`,
  `OfferCountered` et `OfferExpired`, chacun annoncé une seule fois.
- `Erasure` et `ExchangeErasure` : les réservations d'un membre supprimées, ses offres ouvertes retirées, et
  `erased:member` à sa place dans les transferts et les offres qu'il avait avec d'autres membres.
- `Refusal` et `ExchangeException`, implémentée par `InvalidExchange`, `UnknownReward`, `UnknownExchange`,
  `ExchangeConflict`, `ExchangeClosed` et `ExchangeRefused`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
