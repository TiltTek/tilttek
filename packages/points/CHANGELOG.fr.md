<!-- phil-dev source-sha256=b898486190c36f5397f0f465017d405965476b2ef6d68c840ef3e82a35187906 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/points` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.2.0-alpha - 2026-11-14

### Ajouté

- `LotsCheckpoint` : les lots d'un compte dans une monnaie à une position — les lots qui ont encore des points et la
  dette —, avec ses valeurs simples pour un grand livre qui le stocke en JSON ; `Lots::resume()`, `Lots::checkpoint()`
  et `Lots::balance()`.
- `LedgerContention` : levée quand le compte a changé pendant chaque tentative d'expirer ses crédits.

### Modifié

- `Ledger` reçoit `appendAfter()`, qui ajoute un mouvement seulement si le compte débité n'a aucune écriture après une
  position, en une seule étape où un ajout couvert du même compte ne peut pas se glisser, et `keptLots()` et
  `keepLots()`, qui gardent un point de reprise des lots d'un compte ; `forgetDerived()` et `eraseMember()` suppriment
  les points de reprise. Un grand livre de l'application implémente les trois méthodes.
- `Points::expire()` part du point de reprise que le grand livre a gardé et ne lit que les écritures faites après lui,
  au lieu de tout l'historique du compte, puis garde un nouveau point de reprise ; il relit tout l'historique quand le
  point de reprise ne correspond plus au solde.

### Corrigé

- Une dépense ou un transfert qui se glissait entre la lecture des lots par l'expiration et son écriture pouvait laisser
  un solde sous zéro : chaque expiration n'est maintenant écrite que si le compte n'a aucune écriture plus récente, et
  relue sinon.

## 0.1.0-alpha - 2026-11-11

### Ajouté

- `AccountType` et `Account` : les comptes des membres nommés par leur référence `phil/resource`, les comptes d'émission
  nommés par une règle ou une source, les comptes d'échange nommés par ce que paient les points, le compte d'expiration
  et le compte qui représente un membre effacé ; écrits sous la forme `type:name` et relus.
- `Currency` et `Currencies` : des monnaies nommées par l'application, chacune avec la durée de vie de ses crédits sous
  forme de durée ISO 8601 ou sans, la première étant celle par défaut ; `Currencies::fromArray()` à partir d'une
  configuration.
- `MovementKey` et `MovementKind` : la clé qui fait qu'un mouvement n'est écrit qu'une fois, les clés de l'application
  tenues à part des clés préfixées par `#` que dérive la bibliothèque, et les types de mouvement, dont trois dérivés de
  l'activité et recalculés par une reconstruction.
- `Movement`, `LedgerEntry`, `Recorded` et `Erasure` : des points qui passent d'un compte débité à un compte crédité, de
  1 à 10^15, avec le moment où ils comptent, l'activité qui les a causés et le mouvement qu'ils annulent ; l'écriture à
  sa position, ses valeurs simples pour une exportation, et ce qu'ont renvoyé l'écriture et l'effacement.
- `Lot` et `Lots` : ce qui reste de chaque crédit, les débits pris dans le lot qu'ils nomment, puis dans les lots
  vivants les plus anciens, puis dans les autres, et la dette que les crédits suivants paient d'abord.
- `Period`, `Cap`, `EarningRule` et `Rules` : des points par unité de la quantité d'une activité ou par activité, dans
  une monnaie, pour des verbes par motif et des conditions de contexte, plafonnés par membre sur un jour, une semaine
  ISO, un mois, une année ou depuis toujours, comptés dans le fuseau horaire des règles ; `Rules::fromArray()` à partir
  d'une configuration.
- `Ledger`, `InMemoryLedger` et `Reading` : un mouvement écrit une seule fois par clé et sa clé refusée pour un autre
  mouvement, un paiement écrit seulement quand le solde le couvre, les soldes, le net entre deux comptes sur une
  période, des relevés dans l'ordre, les membres crédités avant une date limite, les mouvements dérivés oubliés avant
  une reconstruction, et l'effacement d'un membre qui garde les transferts avec les autres membres.
  `InMemoryLedger::reset()` vide le grand livre, pour un processus de longue durée.
- `Points` : soldes et historiques par monnaie, octrois et révocations depuis une source, dépenses pour un échange,
  remboursements, transferts entre membres, les crédits dus expirés avant qu'un paiement soit vérifié, l'expiration d'un
  membre ou de tous les membres, et l'effacement d'un membre.
- `PointsProjection` : une projection de `tilttek/activity` qui transforme chaque activité enregistrée en points que
  donnent ses règles, une seule fois par règle et par activité, les annule quand l'activité est annulée, et oublie les
  mouvements dérivés d'une période avant une reconstruction.
- `PointsException`, implémentée par `InvalidPoints`, `MovementConflict`, `InsufficientPoints`, `UnknownCurrency` et
  `UnknownMovement`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
