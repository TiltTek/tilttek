<!-- phil-dev source-sha256=82e7292c008ed0262c83eee944a0e22d07929baa0a7fec1eb8cf37fbbca4c0e9 -->
<!-- phil-dev source-sha256=0 -->
# La boîte

Chaque membre a une seule boîte. Elle contient des entrées, chacune étant un `Achievement` avec la `sequence` sous
laquelle la boîte l'a reçu, son `EntryState`, le moment où il a été reçu et le moment où il a été réglé.

## Réussites

Un `Achievement` désigne ce qu'un membre a obtenu sans dépendre du paquet qui l'a donné :

| Champ | Ce qu'il contient | Limites |
|---|---|---|
| `member` | La `ResourceReference` du membre, `member:42` | — |
| `source` | La clé du paquet ou de la fonctionnalité qui l'a donné : `badges`, `quests`, `progression` | Une clé |
| `kind` | Le genre de récompense dans cette source : `badge`, `tier`, `level`, `milestone`, `quest`, `set` | Une clé |
| `subject` | L'identifiant de ce qui a été obtenu dans cette source : `badge.first-roll`, `track.reader` | De 1 à 191 caractères ASCII visibles, sans espace |
| `occurrence` | Ce qui le rend unique pour cette source et ce membre : `badge.first-roll#1`, une deuxième attribution `#2` | De 1 à 191 caractères ASCII visibles, sans espace |
| `earnedAt` | Le moment où il a été obtenu, qui ordonne la boîte | — |
| `parameters` | Ce dont son message a besoin — `['level' => 5]` —, triés par nom | Au plus 10, chacun nommé par une clé, un entier ou de 1 à 100 caractères UTF-8 |
| `showUntil` | Le moment après lequel il ne vaut plus la peine d'être montré, ou aucun | Après `earnedAt` |

Une clé est faite de 1 à 96 lettres minuscules, chiffres, `_`, `-`, ou points simples entre eux ; le sujet et
l'occurrence prennent tels quels les codes propres du paquet de récompenses. L'application transforme la source, le
genre et le sujet en message par ses traductions, `celebrations.badges.badge` avec le nom du badge, et garde ainsi tout
texte hors de la boîte.

## Recevoir une seule fois

`receive()` garde une réussite dans la boîte de son membre, en attente, et renvoie son `Entry`. Une réussite signalée de
nouveau — les mêmes membre, source et occurrence — est gardée une seule fois : `receive()` renvoie l'entrée gardée, dans
l'état où elle se trouve maintenant, et ne signale rien. Les paquets de récompenses informent leurs observateurs au
moins une fois : un avis peut donc venir deux fois, et la boîte rend cela sans conséquence.

Une réussite dont le `showUntil` est déjà passé quand elle arrive est gardée comme caduque, pour l'historique, et n'est
jamais montrée.

## Groupes et ordre

`pending()` renvoie les entrées en attente du membre en `Group`s d'une source et d'un genre. Dans un groupe, et entre
les groupes, la plus ancienne obtenue vient en premier, les égalités dans l'ordre où la boîte les a reçues ; un groupe
vient là où vient sa plus ancienne entrée. Un groupe montre ses premières entrées — 10 par défaut,
`Limits::$shownPerGroup` — et les compte toutes : `count`, `hidden()` pour celles au-delà des premières, `isSingle()`
pour un groupe d'une seule entrée, montrée seule plutôt que comme « 1 nouveau badge ».

`count()` renvoie combien d'entrées attendent d'être montrées, pour la cloche.

## Vues

La page du membre marque des entrées comme vues une fois que la fenêtre les a montrées :

| Méthode | Marque comme vues |
|---|---|
| `markSeen($member, $sequences)` | Les entrées en attente de ces séquences, `$group->sequences()` — au plus autant de séquences que `Limits::$pendingPerMember` |
| `markGroupSeen($member, $source, $kind)` | Toute entrée en attente de cette source et de ce genre, y compris celles que le groupe n'a pas montrées |
| `markAllSeen($member)` | Toute entrée en attente |

Chacune renvoie combien d'entrées elle a marquées. Une séquence qui n'est pas en attente — déjà vue, retirée, caduque,
inconnue — est ignorée : une page envoyée deux fois est donc sans conséquence.

## Retirées

`withdraw($member, $source, $occurrence)` règle une entrée dont la récompense a été reprise — un badge révoqué, un
niveau perdu après une activité annulée — : en attente, elle n'est jamais montrée ; vue, l'historique la montre comme
retirée. Elle renvoie false, sans rien faire, pour une entrée que la boîte ne détient pas ou détient déjà comme
retirée. Une réussite reçue de nouveau après son retrait reste retirée : une récompense donnée de nouveau vient avec une
nouvelle occurrence — `#2` —, comme les paquets de récompenses numérotent leurs attributions.

## Caduques

Une entrée devient caduque — sa récompense demeure — dans deux cas :

- son `showUntil` est venu pendant qu'elle attendait : elle est lue comme caduque dès ce moment, par `pending()`,
  `count()`, `next()` et `history()`, et réglée comme caduque dans le dépôt quand une réussite en attente plus tardive
  arrive pour ce membre ;
- les entrées en attente du membre ont atteint leur limite — 200 par défaut, `Limits::$pendingPerMember` — quand une
  autre arrive : les plus anciennes obtenues deviennent caduques, afin qu'une de plus tienne.

## Limites et historique

La boîte garde au plus `Limits::$entriesPerMember` entrées — 500 par défaut. Quand une nouvelle entrée dépasse la
limite, les premières reçues des entrées réglées — vues, retirées ou caduques — sont retirées ; les entrées en attente
ne le sont jamais, car leur limite n'est jamais supérieure à celle-ci.

`history($member, $limit, $before)` lit une page de la boîte dans tous les états, la dernière reçue en premier : de 1 à
100 entrées, 50 par défaut, celles reçues avant la séquence `$before` — la séquence de la dernière entrée de la page
précédente — quand elle est donnée.

Les séquences sont données par membre et jamais réutilisées, même après le retrait d'une entrée ou l'oubli du membre.

## Effacement

`forget($member)` retire la boîte et les préférences du membre. Les récompenses elles-mêmes vivent dans leurs propres
paquets, qui les effacent par leurs propres moyens.
