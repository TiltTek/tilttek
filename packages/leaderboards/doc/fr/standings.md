<!-- phil-dev source-sha256=149309e5a6557758f4f1c0397881ddf4e0ae1394a1c5c202eae28c7418cac538 -->
# Classements affichés

## Un classement tel qu'un spectateur le voit

```php
$standing = $leaderboards->standing('games-month', viewer: $member, period: '2026-10', limit: 20);
```

`standing()` lit le classement dans une période (celle qui est en cours quand aucun code n'est donné) et retourne un
`Standing` : le classement, la période, ses `limit` premières lignes (de 1 à 100, 10 par défaut) sous forme de `Row`,
la ligne du spectateur et le nombre de membres classés. Chaque `Row` contient son rang et son score, et qui les détient,
tel que le spectateur peut le savoir ([Paraître dans un classement](appearance.md)).

La ligne du spectateur est présente même quand elle se trouve loin sous les premières ; `Standing::ownIsListed()` dit
si elle figure aussi parmi elles, pour qu'un gabarit ne l'affiche qu'une fois. Un spectateur sans score n'a pas de
ligne propre, et un classement anonyme (sans spectateur) n'en a pas non plus.

## Le rang d'un membre

`rank()` retourne la ligne propre d'un membre dans un classement et une période, ou null quand il n'y a aucun score.

## Égalités et critères de départage

Les lignes qui ont le même score sont ordonnées par les critères de départage du classement, puis par le premier à
avoir atteint le score, ou partagent un rang quand les égalités du classement sont `shared` et que les lignes sont
égales sur le score et sur chaque critère de départage ; voir [Classements et périodes](boards-and-periods.md#ties). Un
rang partagé saute autant de places après lui : 1, 1, 3.

## Un classement par objet

Dans un classement par objet, chaque objet (un jeu, un livre, un niveau) a son propre classement, que `standing()`,
`rank()`, `snapshot()`, `frozen()` et `teams()` lisent selon l'objet qu'on leur donne :

```php
$standing = $leaderboards->standing('game-records', $member, object: ResourceReference::fromString('game:catan'));
```

Un classement par objet demandé sans objet, ou un autre classement demandé avec un objet, lève `InvalidLeaderboard`.

## Cercles

Un classement entre amis ou à l'intérieur d'un groupe (un club, une table) est un filtre sur les membres, donné par
l'application sous forme de `Circle` de 1 à 1000 membres :

```php
$friends = Circle::of('member:44', 'member:7', 'member:9');
$standing = $leaderboards->standing('games-month', $member, among: $friends);
```

Les membres sont classés entre eux (le premier du cercle se classe premier), les participants sont ceux du cercle, et
un spectateur hors du cercle n'a pas de ligne propre. Tilttek ne garde ni amitié ni groupe : l'application les connaît.

## Classements et périodes inconnus

Un classement inconnu lève `UnknownBoard` ; un code que le calendrier du classement ne connaît pas, ou la période en
cours d'un classement entre deux saisons, lève `UnknownPeriod`.
