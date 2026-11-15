<!-- phil-dev source-sha256=04ca94924920de37ac55ae3cff8c87b0663949a306327cacb7121ab561221340 -->
# Utilisation

## Les sortes de jetons

Les `TableRules` d'une table énumèrent les sortes de jetons qu'elle distribue, 16 au plus, et ses meneurs de jeu, 16 au
plus. Un `TokenKind` a :

| Champ | Sens | Valeur par défaut |
|---|---|---|
| `key`, `name` | La clé, de 1 à 64 caractères parmi `a-z`, `0-9` et `-` à l'intérieur, et le nom affiché | — |
| `cap` | Combien un détenteur en garde au plus, de 1 à 1 000 | — |
| `scope` | `character` ou `player` : à qui sont les jetons, et donc ce que compte le plafond | `character` |
| `refresh`, `refresh_to` | `never`, ou `each-session` jusqu'à `refresh_to`, de 1 au plafond | `never`, 0 |
| `carry_over` | Combien un détenteur en garde d'une session à la suivante, de 0 au plafond, ou null pour tous | null |
| `grants` | `game-masters` ou `anyone` | `game-masters` |
| `transferable` | S'ils passent d'un détenteur à un autre | false |

Quelques tables, écrites comme `TableRules::fromJson()` les lit :

```json
{
    "kinds": [
        {"key": "inspiration", "name": "Inspiration", "cap": 1, "transferable": true},
        {"key": "bennies", "name": "Bennies", "cap": 5, "scope": "player", "refresh": "each-session",
         "refresh_to": 3, "carry_over": 0, "grants": "anyone", "transferable": true},
        {"key": "fate", "name": "Fate points", "cap": 10, "refresh": "each-session", "refresh_to": 3}
    ],
    "game_masters": ["member:7"]
}
```

L'inspiration se garde et ne revient jamais d'elle-même ; trois bonus reviennent à chaque session et aucun n'est
gardé ; les points de destin remontent jusqu'à trois, et ceux au-dessus sont gardés. `fromJson()` refuse, avec
`InvalidTokenRule`, un champ qu'il ne connaît pas, une valeur du mauvais type, deux sortes de même clé, un montant de
rafraîchissement sur une sorte jamais rafraîchie, et un texte de plus de 64 Kio ou de plus de 6 niveaux de profondeur.

## Octroyer, dépenser, passer

```php
$tokens->grant($table, 'fate', $ana, 2, $gm, 'op-1', 'A clever plan');
$tokens->spend($table, 'fate', $ana, 1, $ana, 'op-2', 'Reroll');
$tokens->transfer($table, 'bennies', $ana, $bo, 1, $ana, 'op-3');
```

| Opération | Qui le peut | Refusée quand |
|---|---|---|
| `grant()` | Un meneur de jeu ; n'importe qui pour une sorte octroyée par `anyone` | Le détenteur dépasserait le plafond |
| `spend()` | Un meneur de jeu, ou un acteur qui contrôle le détenteur | Le détenteur a moins de jetons |
| `transfer()` | Un meneur de jeu, ou un acteur qui contrôle le donneur | La sorte n'est pas transférable, le donneur en a moins, le receveur dépasserait le plafond, ou les deux sont le même |

Un montant va de 1 au plafond de sa sorte. Une raison, si elle est donnée, compte de 1 à 280 caractères, sans caractère
de contrôle. Chaque opération retourne un `Receipt` : les entrées qu'elle a écrites, le solde qu'elle a laissé à son
premier détenteur, et `replayed` quand elle avait déjà été faite.

## L'identifiant d'une opération

L'appelant nomme chaque opération, de 1 à 64 caractères parmi les lettres, les chiffres, `.`, `_`, `:` et `-` — un ULID
créé quand le formulaire est dessiné, par exemple. Le même identifiant avec les mêmes valeurs redonne ce que l'opération
a écrit, n'écrit rien de plus et ne répartit rien ; avec d'autres valeurs, il est refusé avec `TokenRefused`. Une
dépense renvoyée après l'épuisement des jetons reste une reprise, pas un refus.

## Les sessions

```php
$tokens->startSession($table, 'session-14', [$newPlayer], $gm, 'op-9');
```

Un meneur de jeu commence une session une seule fois. Pour chaque sorte et chaque détenteur — ceux que le registre
nomme, et ceux qu'on donne, qui se joignent à la table —, le détenteur garde ce qu'il a, ou au plus la conservation,
puis une sorte rafraîchie à chaque session le ramène à son montant de rafraîchissement. Un détenteur dont les jetons ne
changent pas n'a aucune entrée ; le début lui-même est toujours écrit. La même session commencée par une autre
opération est refusée.

## Lire le registre

```php
$tokens->balance($table, 'fate', $ana);    // 1
$tokens->balances($table);                 // every holder and kind the ledger names, zero included
$tokens->log($table);                      // every entry, by position
$tokens->log($table, $ana);                // the entries of one holder
```

Une `LedgerEntry` donne sa position, l'opération qui l'a écrite, son `EntryType`, la sorte et le détenteur, le
changement et le solde qu'elle a laissé, l'acteur, l'heure en UTC, la raison et la session. `toArray()` l'écrit pour la page de
vérification ou le stockage, et `fromArray()` la relit.

## Effacer un membre

```php
$erased = $tokens->eraseHolder(ResourceReference::of('member', '8'));   // HolderErased, or null
```

Quand un membre part, sa référence cède la place à un pseudonyme dans chaque entrée de chaque registre, comme détenteur
et comme acteur : `erased:` et 32 chiffres hexadécimaux aléatoires, tirés pour cet effacement seul, de sorte que deux
membres effacés ne se distinguent jamais l'un de l'autre et ne se rattachent jamais à leur compte. Les positions, les
changements, les soldes et l'ordre restent : la vérification des autres détenteurs s'additionne encore, et les jetons
que le membre détenait restent au pseudonyme. C'est la seule réécriture d'un registre qui, autrement, ne fait que
grandir.

Un effacement envoyé de nouveau ne trouve aucune entrée qui nomme le membre, n'écrit rien, ne répartit rien et renvoie
null. Sinon `HolderErased` est réparti, avec le pseudonyme et le nombre d'entrées réécrites, puis renvoyé ; il ne nomme
ni le membre ni une table. Une opération du membre envoyée de nouveau après l'effacement ne correspond plus à ce qui a
été écrit, et est refusée comme un identifiant réutilisé.

Les raisons sont gardées telles qu'elles ont été écrites : une application qui laisse les joueurs les saisir en exclut
les noms, ou offre des raisons à choisir dans une liste. Les meneurs de jeu nommés dans les règles d'une table sont à
l'application de les changer.

## Les opérations simultanées

La bibliothèque lit la dernière position du registre, vérifie l'opération selon les soldes, et ajoute ses entrées
juste après cette position. Un stockage qui y trouve une autre entrée refuse l'ajout avec `LedgerConflict` ; la
bibliothèque relit alors — l'opération faite entre-temps sous le même identifiant est une reprise — et vérifie de
nouveau, trois fois en tout, avant que le conflit n'atteigne l'appelant, qui peut réessayer plus tard. Un registre
prend 100 000 entrées au plus.
