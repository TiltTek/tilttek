<!-- phil-dev source-sha256=915a2b17abec9d116ad104f5cd84fd96b55d5efb3143fb10b4a9a4e99acb9c46 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/referrals` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-26

### Ajouté

- `Referrals` : le code de parrainage aléatoire d'un membre, tiré une seule fois et tiré de nouveau quand le stockage le
  trouve déjà pris ; un nouveau venu parrainé par ce code ; la récompense en points de `tilttek/points` des deux côtés,
  jamais en argent, en commission ou en rabais.
- La récompense payée seulement une fois que le nouveau venu a fait une première vraie action au moment du parrainage ou
  après, annoncée par `Contract\FirstActions`, jamais à l'inscription ; `Refusal::NoFirstAction` sinon.
- Les garde-fous de `tilttek/exchange` : aucun parrainage de soi-même, ni d'un autre compte dont `Contract\SamePerson`
  dit qu'il s'agit de la même personne, question posée de nouveau à la récompense ; l'ancienneté d'un parrain et la
  fenêtre d'un nouveau venu, lues par le port `MemberSince` de `tilttek/exchange`, désactivées tant qu'elles ne sont pas
  fixées ; un seul parrainage par nouveau venu.
- Des points donnés une seule fois : le parrainage réglé en une seule étape du stockage, qui garde les montants payés,
  et le mouvement de chaque côté écrit sous une clé construite à partir de l'identifiant aléatoire du parrainage, pour
  qu'une récompense demandée de nouveau, ou par deux processus en même temps, ne paie rien de plus.
- Le plafond de `Terms::$maxRewards` parrainages qui récompensent un parrain, au total, tenu quand deux nouveaux venus
  agissent en même temps ; au-delà, le nouveau venu reçoit quand même ses points ; `remaining()`.
- `Terms` : les points de chaque côté, le plafond, la monnaie, le compte d'émission et les garde-fous sur les dates,
  avec leurs bornes.
- `ReferralCode` en base 32 de Crockford, relu tel qu'une personne le recopie ; `ReferralId` ; `Referral`,
  `ReferralStatus`, `Reward` et `FirstAction`, les moments en UTC.
- La lecture : `referralOf()` et `referralsOf()`, du plus ancien au plus récent, au plus 100 à la fois.
- L'effacement d'un membre : son code et ses parrainages en attente comme nouveau venu supprimés, `erased:member` à sa
  place dans les autres, pour qu'aucun parrainage ne le lie à quiconque.
- `Contract\ReferralStore` avec `Store\InMemoryReferralStore`, `Contract\NoSamePersonSignal`.
- Des exceptions sous une seule interface `ReferralsException` : `ReferralRefused`, `UnknownReferralCode`,
  `ReferralConflict` et `InvalidReferral`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
