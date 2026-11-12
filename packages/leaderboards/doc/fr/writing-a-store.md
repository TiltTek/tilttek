<!-- phil-dev source-sha256=e7d459fca6b0d4e536125ca4c83c200389e93740754faab564843dca3b2abe55 -->
# Écrire un stockage

Une application garde les classements sur son propre support en implémentant quatre contrats, ou garde les scores dans
Valkey avec `ValkeyScoreStore`, plus bas. Chacun est fourni avec un cas de test abstrait dans `tests/Unit/Store/` que
toute implémentation doit réussir : étendez-le et retournez votre stockage depuis `store()`.

## `ScoreStore`

- `add()` garde une contribution et retourne false quand l'activité en a déjà apporté une à ce classement : une clé
  unique sur le classement et l'activité, écrite par une insertion qui n'écrit rien quand la ligne existe.
- `remove()` retire et retourne chaque contribution d'une activité ; `forget()` celles des activités à partir d'un
  moment (toutes, sans moment) ; `expire()` celles d'un classement (chaque clé d'un classement par objet) survenues avant
  un moment ; `contributions()` celles du score d'un membre, dans l'ordre où elles sont survenues. Une contribution
  garde ses critères de départage, de 0 à 2 entiers.
- `save()` remplace le score d'un membre dans un classement et une période, avec ses critères de départage ; `delete()`
  le retire ; `score()` le lit.
- `top()` lit les premiers scores d'un classement dans une période dans l'ordre du `Ranking` donné : la plus haute
  valeur d'abord, chaque critère de départage dans son sens, puis le moment le plus ancien, puis la référence du membre,
  parmi les membres d'un cercle quand il est donné. Un index sur le classement, la période, la valeur, les critères de
  départage et le moment le sert. `Ranking::key()` donne une clé qui se trie octet par octet dans cet ordre, pour un
  stockage qui trie des chaînes.
- `ahead()` compte les scores qui se classent avant un score donné, comme le dit `Ranking::ranksBefore()` : les
  meilleurs et, quand le premier à atteindre un score se classe premier, les égaux atteints avant lui ou au même moment
  par un membre écrit avant. `participants()` compte les scores d'un classement dans une période.
- `of()` et `eraseMember()` lisent et retirent les scores d'un membre pour une exportation et un effacement ;
  `eraseMember()` retire aussi ses contributions.

## `SnapshotStore`

`save()` garde un instantané une fois par classement et par période, et retourne false quand il en existe un ; `find()`
le lit avec ses lignes dans l'ordre des rangs ; `placings()` lit les lignes d'un membre ; `eraseMember()` les retire.

## `AppearanceStore`

`choose()` garde le choix d'un membre (choisir d'être caché l'oublie) ; `of()` lit les choix des membres donnés qui
paraissent, par référence ; `eraseMember()` en oublie un.

## `LeagueStore`

- `tier()` lit le palier d'un membre dans un classement, null avant sa première semaine réglée.
- `join()` place un membre dans une division d'un palier pour une semaine (celle de plus petit numéro de sa cohorte qui
  compte moins de membres que la taille, ou le numéro qui suit le plus haut du palier) et retourne l'appartenance avec
  sa cohorte ; un membre déjà placé cette semaine garde
  la sienne. Une clé unique sur le classement, la semaine et le membre, écrite par une insertion qui n'écrit rien quand
  la ligne existe, place un membre une seule fois.
- `membership()`, `division()` et `memberships()` lisent les appartenances par nom de palier, par division, puis selon
  le moment d'arrivée et la référence du membre.
- `isSettled()` et `unsettled()` disent quelles semaines sont réglées, les non réglées à partir du code le plus ancien.
- `settle()` écrit ensemble chaque déplacement, chaque nouveau palier et la marque de la semaine, et retourne false,
  sans rien écrire, quand la semaine est déjà réglée : une clé unique sur le classement et la semaine, insérée en
  premier dans une seule transaction.
- `tiersOf()`, `membershipsOf()` et `eraseMember()` lisent et retirent les paliers et les appartenances d'un membre.

## Scores dans Valkey

`Store\Valkey\ValkeyScoreStore` garde les contributions et les scores dans Valkey, ou dans un serveur Redis qui parle
les mêmes commandes, par une `Contract\ValkeyConnection` : une méthode qui envoie une commande et retourne sa réponse.
`RawCommandConnection` enveloppe le client que l'application a déjà (l'objet `Redis` de l'extension `redis` de PHP,
Relay ou Predis) :

```php
$store = new ValkeyScoreStore(new RawCommandConnection($redis), $boards, prefix: 'leaderboards');
```

Chaque classement est un ensemble trié dont les clés ont toutes le score 0 et se trient comme le classement, par
`Ranking::key()` : les premières lignes se lisent par `ZRANGE`, le rang d'un membre par `ZLEXCOUNT` (les clés avant la
sienne, ou avant son score et ses critères de départage seuls pour un rang partagé), et le nombre de classés par
`ZCARD`, chacun en un logarithme de la taille du classement. Un classement parmi un cercle, ou demandé dans d'autres
sens que ceux du classement, est ordonné en mémoire à partir des scores de ses membres. Chaque écriture qui touche
plusieurs clés s'exécute en un seul script Lua ; chaque clé commence par le préfixe entre accolades, une étiquette de
hachage qui les garde sur un seul nœud d'une grappe. Les instantanés, les choix et les ligues restent dans un autre
stockage.
