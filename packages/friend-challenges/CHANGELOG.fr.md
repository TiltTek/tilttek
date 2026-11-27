<!-- phil-dev source-sha256=4998e071cbb80ebd4d7784741b33229f84fe8cdbe3aab5015db54a3f3c965934 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/friend-challenges` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-20

### Modifié

- `tilttek/points` 0.2 requis : 0.1 n'est plus accepté.

## 0.1.0-alpha - 2026-11-14

### Ajouté

- `FriendChallenges` : des défis créés avec leur prix retenu, la même création envoyée de nouveau rendant le même défi ;
  les invités vérifiés comme amis ; la participation, le refus invisible pour le créateur, les preuves et leur examen
  par le créateur, par un vote ou par un modérateur ; l'annulation avant toute preuve, l'expiration une fois l'examen
  terminé, `expireDue()` pour une tâche planifiée ; les signalements aux modérateurs, le gel, le dégel, le retrait d'un
  défi ou d'une preuve ; chaque étape gardée à la version suivante seulement, essayée trois fois contre d'autres
  auteurs, réglée dans le séquestre, puis annoncée ; `settle()` pour refaire un mouvement du séquestre après un échec ;
  `erase()` pour retirer un membre de ses défis par lots bornés.
- `Pseudonym` : le défi ouvert ou gelé d'un créateur effacé retiré et son prix rendu, les preuves en attente d'un
  participant effacé retirées, puis une référence du type `erased`, tirée pour chaque défi, à leur place ; un défi dont
  le créateur ou le gagnant a été effacé n'est plus jamais réglé ; une référence du type `erased` donnée par un
  appelant — créateur, invité, participant, juge, auteur d'un signalement, modérateur, membre à effacer — refusée avec
  `InvalidChallenge` avant que rien soit écrit ou signalé ni qu'aucun point bouge.
- `Challenge`, `ChallengeState`, `Visibility`, `Validation`, `Proof` et `ProofState` : une machine à états explicite —
  ouvert, gelé, gagné, annulé, expiré, retiré —, chaque étape vérifiée pour son membre, son état et son heure ; 50
  invités, 100 participants, 200 preuves et 5 par participant au plus ; les heures gardées en UTC à la microseconde et
  ordonnées, jamais comparées avec `==` ou `===` ; un enregistrement écrit et relu, vérifié champ par champ et dans son
  ensemble.
- `ChallengeDraft`, `ChallengeRules` et `Prize` : identifiants, titres et conditions vérifiés ; un défi entre amis par
  défaut ; une durée, un examen et un prix maximal fixés par le site ; un prix dans une unité de points, jamais
  d'argent.
- `Contract\PrizeEscrow`, `PrizeHold` et `Payout` : le prix réservé, confirmé au gagnant avec le reste rendu, ou annulé,
  chaque mouvement sous la clé de sa retenue et suivant le contrat `PointsHold` de `tilttek/exchange-bundle` ;
  `Escrow\PointsPrizeEscrow` sur le grand livre de `tilttek/points` et `Escrow\InMemoryPrizeEscrow`, avec un adaptateur
  sur `tilttek/exchange-bundle` dans la documentation.
- `Contract\ChallengeStore`, `Contract\Friendship` et `Contract\Moderation`, avec `Report` et `ReportReason`, et leurs
  implémentations en mémoire ; le stockage énumère les défis qui nomment un membre.
- `Event\ChallengeCreated`, `MemberInvited`, `ParticipantJoined`, `ProofSubmitted`, `ProofDecided`, `ChallengeWon`,
  `ChallengeEnded`, `ChallengeFrozen` et `ChallengeUnfrozen`, distribués par PSR-14 et qui ne portent que des
  identifiants ; `ChallengeWon` pour la célébration du kit PHIL UX.
- `Exception\FriendChallengesException`, avec `InvalidChallenge`, `ChallengeRefused`, `ChallengeConflict` et
  `EscrowConflict`.
- Des cibles de fuzzing pour l'enregistrement d'un défi et pour sa machine à états, et des bancs d'essai d'un défi
  plein.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
