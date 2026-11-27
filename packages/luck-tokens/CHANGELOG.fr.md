<!-- phil-dev source-sha256=545b146ee2182e712087f987d4b1802ff2687aa8dbfc0ab4311b5942ad55870b -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/luck-tokens` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.2-alpha - 2026-11-25

### Modifié

- `LuckTokens::eraseHolder()`, `LedgerEntry::pseudonymized()` et `InMemoryTokenLedger::eraseHolder()` vident la raison
  des écritures que détient le membre : une raison est écrite au sujet de son détenteur, et l'audit des soldes n'a
  besoin que des changements. Les raisons des écritures que le membre a faites pour d'autres restent. Un `TokenLedger` à
  vous fait de même.

## 0.1.1-alpha - 2026-11-20

### Corrigé

- La limite d'écritures d'un registre est vérifiée à mesure que chaque écriture est construite : `startSession()` pour
  de nombreux détenteurs s'arrête à la première écriture qui la dépasse, plutôt que de construire chaque renouvellement
  avant de tous les refuser.

## 0.1.0-alpha - 2026-11-15

### Ajouté

- `LuckTokens` : des jetons accordés par un meneur de jeu ou, pour un type qui le permet, par n'importe qui ; dépensés
  par leur détenteur, par un acteur qui les contrôle ou par un meneur de jeu ; passés d'un détenteur à un autre ; fixés
  au début d'une séance par report et renouvellement pour les détenteurs du registre et ceux qui sont donnés ; un solde
  jamais sous zéro ni au-dessus de son plafond ; des opérations nommées par l'appelant, renvoyées sans rien écrire de
  plus, un identifiant réutilisé avec d'autres valeurs refusé ; ajoutées après la dernière position lue, lues et
  vérifiées de nouveau après un conflit, trois fois en tout ; un registre plein à 100 000 écritures ; les soldes, le
  registre et les écritures d'un détenteur pour l'audit ; un membre effacé de chaque registre sous un pseudonyme tiré
  pour l'effacement, `erased:` et 32 chiffres hexadécimaux aléatoires, comme détenteur et comme acteur, les soldes, les
  positions et l'ordre laissés tels quels, un membre déjà effacé laissé tranquille.
- `TokenKind`, `HolderScope`, `Refresh`, `GrantPolicy` et `TableRules` : des types avec une clé, un nom, un plafond
  jusqu'à 1 000, une portée, un renouvellement, un report, qui accorde et s'ils se passent ; 16 types et 16 meneurs de
  jeu par table ; lus à partir de valeurs simples ou de JSON dans les limites de 64 Kio et de 6 niveaux, un champ
  inconnu refusé, et réécrits.
- `Ledger\LedgerEntry` et `Ledger\EntryType` : les écritures du registre avec leur position, leur opération, leur type,
  leur type de jeton, leur détenteur, leur changement, leur solde, leur acteur, leur heure en UTC, leur raison et leur
  séance ; écrites et relues en valeurs simples, vérifiées ; l'écriture avec un pseudonyme à la place d'un membre, par
  `pseudonymized()`.
- `Receipt` et `Balance` : ce qu'une opération a écrit et le solde qu'elle a laissé, et le solde d'un détenteur.
- `Event\TokensGranted`, `Event\TokensSpent`, `Event\TokensTransferred` et `Event\SessionStarted`, distribués par PSR-14
  une fois les écritures faites, et `Event\HolderErased`, avec le pseudonyme et le nombre d'écritures réécrites, qui ne
  nomme ni le membre ni une table.
- `Contract\TokenRules`, `Contract\TokenLedger` — avec `eraseHolder()`, la seule réécriture du registre — et
  `Contract\HolderAccess`, avec `Store\InMemoryTokenRules`, `Store\InMemoryTokenLedger` — ses soldes, ses détenteurs et
  ses séances indexés — et `Store\SelfAccess`.
- `Exception\LuckTokensException`, avec `InvalidTokenRule`, `TokenRefused` et `LedgerConflict`.
- Une cible de fuzzing pour les règles d'une table, et des bancs d'essai d'une dépense après 10 000 écritures et du
  début d'une séance.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
