<!-- phil-dev source-sha256=0745d5827811fc14f88f508e8e5c478715b694ea306346b1ee25f3f6fd8fad12 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/bounties` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-20

### Modifié

- `tilttek/points` 0.2 requis : 0.1 n'est plus accepté.

## 0.1.0-alpha - 2026-11-14

### Ajouté

- `Bounties` : des primes offertes avec leurs points retenus, la même offre envoyée de nouveau rendant la même prime,
  une prime ouverte par sujet et cinq primes ouvertes par commanditaire par défaut ; les réclamations, les
  attributions — à une seule réclamation ou partagées —, la clôture avec le reste rendu, l'annulation avant toute
  réclamation, le règlement une fois la grâce passée, `expireDue()` pour une tâche planifiée ; chaque étape gardée à la
  version suivante seulement, essayée trois fois contre d'autres auteurs, réglée dans le séquestre, puis annoncée ;
  `settle()` pour refaire un mouvement du séquestre après un échec ; `erase()` pour retirer un membre de ses primes par
  lots bornés.
- `Pseudonym` : la prime ouverte d'un commanditaire effacé réglée, les réclamations d'un réclamant effacé sur une prime
  ouverte retirées avec leurs attributions, puis une référence du type `erased`, tirée pour chaque prime, à leur place ;
  une prime dont le commanditaire ou un gagnant a été effacé n'est plus jamais réglée ; une référence du type `erased`
  donnée par un appelant — commanditaire, réclamant, membre à effacer, sujet d'un brouillon — refusée avec
  `InvalidBounty` avant que rien soit écrit ni qu'aucun point bouge.
- `Bounty`, `BountyState`, `Claim` et `Award` : une machine à états explicite — ouverte, attribuée, expirée, annulée —,
  chaque étape vérifiée pour son membre, son état et son heure ; réglée tout de suite quand tout le montant est
  attribué ; 200 réclamations, 5 par membre et 20 attributions au plus ; les paiements regroupés par gagnant ; les
  heures gardées en UTC à la microseconde et ordonnées, jamais comparées avec `==` ou `===` ; un enregistrement écrit et
  relu, vérifié champ par champ et dans son ensemble.
- `BountyDraft`, `BountyReason`, `BountyRules` et `Prize` : une durée requise et bornée, une grâce, un montant minimal
  et maximal et les primes ouvertes par commanditaire fixés par le site ; une raison tirée d'une liste ; un montant dans
  une unité de points, jamais d'argent, sans champ ni adaptateur pour l'argent.
- `Contract\BountyEscrow`, `BountyHold` et `Payout` : les points réservés, confirmés aux gagnants avec le reste rendu,
  ou annulés, chaque mouvement sous la clé de sa retenue et suivant le contrat `PointsHold` de
  `tilttek/exchange-bundle` ; `Escrow\PointsBountyEscrow` sur le grand livre de `tilttek/points` et
  `Escrow\InMemoryBountyEscrow`, avec un adaptateur sur `tilttek/exchange-bundle` dans la documentation.
- `Contract\BountyStore`, avec `Store\InMemoryBountyStore` : la prime ouverte d'un sujet, les primes ouvertes d'un
  commanditaire, et les primes qui nomment un membre.
- `Event\BountyOffered`, `BountyClaimed`, `AwardGiven`, `BountyAwarded` et `BountyClosed`, distribués par PSR-14 et qui
  ne portent que des identifiants ; `BountyAwarded` pour la célébration du kit PHIL UX.
- `Exception\BountiesException`, avec `InvalidBounty`, `BountyRefused`, `BountyConflict` et `EscrowConflict`.
- Des cibles de fuzzing pour l'enregistrement d'une prime et pour sa machine à états, et des bancs d'essai d'une prime
  pleine.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
