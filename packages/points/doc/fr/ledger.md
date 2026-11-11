<!-- phil-dev source-sha256=1838a84b04b9f15b5d7532e35acbd929b53eac73aa3c0e19992f70ab1901a82f -->
# Le registre

## Comptes

Chaque mouvement va d'un `Account` à un autre. Le type du compte dit ce qu'il représente :

| Type | Nommé par | Écrit | Son solde |
|---|---|---|---|
| `Member` | la référence du membre | `member:member:44` | Les points du membre |
| `Issuance` | la règle ou la source qui émet | `issuance:games`, `issuance:manual` | Négatif de ce qu'il a donné |
| `Redemption` | ce que les points paient | `redemption:reward:12` | Ce qui y a été dépensé |
| `Expiry` | — | `expiry:expired` | Ce qui a expiré |
| `Erased` | — | `erased:member` | Ce que les membres effacés ont donné à d'autres ou reçu d'eux |

`Account::fromString()` relit ce que `toString()` a écrit. Un nom autre que celui d'un membre compte 1 à 255 lettres
minuscules, chiffres, `_`, `.`, `:` ou `-`, et commence par une lettre ou un chiffre.

## Mouvements

Un `Movement` déplace de 1 à 10^15 points d'une devise de son compte débité vers son compte crédité, qui sont
différents. Outre sa clé et son genre, il porte :

- `occurredAt`, le moment où il compte : le moment de l'activité pour les points gagnés et renversés, la fin de la durée
  de vie d'un crédit pour les points expirés, et sinon le moment où il a été demandé ;
- `cause`, la clé d'idempotence de l'activité qui a rapporté ou renversé les points ;
- `reverses`, la clé du mouvement qu'il défait (le gain qu'un renversement annule, la dépense qu'un remboursement
  rend), ou du crédit qu'il fait expirer.

| Genre | De | Vers | Écrit par |
|---|---|---|---|
| `Earned` | le compte d'émission de la règle | le membre | `PointsProjection` |
| `Reversed` | le membre | le compte d'émission de la règle | `PointsProjection`, lors d'une annulation |
| `Expired` | le membre | `expiry:expired` | `Points::expire()` |
| `Granted` | le compte d'émission d'une source | le membre | `Points::grant()` |
| `Revoked` | le membre | le compte d'émission d'une source | `Points::revoke()` |
| `Spent` | le membre | un compte de récompense | `Points::spend()` |
| `Refunded` | le compte de récompense | le membre | `Points::refund()` |
| `Transferred` | un membre | un autre membre | `Points::transfer()` |

Les trois premiers sont *dérivés* : ils découlent de l'activité et des soldes, et une reconstruction les recalcule. Les
autres sont *décidés*, et rien ne les recalcule.

## Clés

Une `MovementKey` fait qu'un mouvement n'est écrit qu'une fois : le même mouvement écrit de nouveau sous sa clé
retourne l'entrée déjà présente, et la même clé pour un autre mouvement (autre genre, devise, compte ou montant) lève
`MovementConflict`. Le moment est exclu de la comparaison : une opération demandée de nouveau plus tard reste un
doublon.

L'application donne la clé de chaque opération qu'elle décide, construite à partir de ce qui rend l'opération unique
(`order:2026-0193:redeem`, `gift:2026-11-07:44:12`), jamais à partir du moment où elle s'exécute. Les clés que la
bibliothèque dérive commencent par `#`, par quoi les clés de l'application ne peuvent pas commencer :
`#earn:<rule>:<activity key>`, `#reverse:<earning key>`, `#expire:<credit key>`, `#refund:<spending key>`. Une clé
compte de 1 à 400 octets, sans espace blanc ni caractère de contrôle.

## Soldes et historiques

```php
$points->balance($member);            // in the default currency
$points->balance($member, 'stars');
$points->history($member);            // the 50 first movements, oldest first
$points->history($member, 'stars', after: 120, limit: 100);
```

Un solde est la somme des mouvements du compte dans la devise : ce qui lui a été crédité moins ce qui lui a été débité.
Un historique se lit par position, au plus 1 000 mouvements à la fois ; `LedgerEntry::toArray()` donne chaque ligne en
valeurs simples, et `Movement::amountFor($account)` le montant signé pour le membre.
