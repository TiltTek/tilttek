<!-- phil-dev source-sha256=11b33f9989076ea9bc236b75f278ff7e8e9b46cf3f5a9f5aad5bcfdb394dc831 -->
# Statistiques

`PlayStatistics` calcule sur demande les statistiques d'un membre ou d'un groupe, à partir des parties que donne le
stockage, et ne garde rien :

```php
$statistics = new PlayStatistics($store);

$member = $statistics->ofMember('member-1');
$season = $statistics->ofGroup('thursday-club', new DateTimeImmutable('2027-01-01'), new DateTimeImmutable('2027-07-01'));
```

`ofMember()` lit les parties où le membre détient une place confirmée, et `ofGroup()` les parties du groupe. Les deux
prennent une période, de son début, inclus, à sa fin, exclue, l'un ou l'autre ouvert ; une période exclut les parties
dont la date est inconnue.

## Ce que contient un résultat

| `Statistics` | Ce qu'il contient |
|---|---|
| `plays` | Les parties lues. |
| `wins` | Les parties que le membre a gagnées ; null pour un groupe. |
| `unfinished` | Les parties laissées inachevées. |
| `minutes`, `timedPlays` | Les minutes des parties dont la durée est connue, et leur nombre ; `averageMinutes()` donne la moyenne en minutes entières, ou null. |
| `games` | Une `GameStatistics` pour chaque jeu joué, le plus joué d'abord, puis par identifiant ; `game()` en trouve un. |
| `players` | Une `PlayerStatistics` pour chaque membre avec qui l'on a joué — pour un membre, les autres qui détenaient une place confirmée à ses côtés ; pour un groupe, ses membres — avec les parties et les victoires, au plus 50, le plus de parties d'abord, puis par identifiant. |
| `hIndex` | Le plus grand nombre de jeux joués au moins autant de fois que leur nombre : 3 quand trois jeux ont été joués trois fois ou plus, mais pas quatre jeux quatre fois. |
| `truncated` | Si plus de parties correspondaient que celles qui ont été lues. |

| `GameStatistics` | Ce qu'elle contient |
|---|---|
| `game` | L'identifiant du jeu dans le catalogue. |
| `plays` | Ses parties. |
| `wins` | Les parties de ce jeu que le membre a gagnées ; null pour un groupe. |
| `minutes` | Les minutes de ses parties dont la durée est connue. |
| `lastPlayed` | La date la plus récente d'une partie de ce jeu, ou null quand aucune date n'est connue. |
| `highestScore` | Le meilleur score des places du membre, ou, pour un groupe, de chaque place confirmée ; null quand aucune n'a de score. |

Seules les places confirmées comptent : une place qui attend son membre, ou anonyme, s'ajoute aux parties et aux
minutes du groupe, jamais à celles d'un membre. Une victoire suit le [résultat](plays.md#places) de la partie.

## Bornes

Au plus 10 000 parties sont lues pour un résultat, les plus récentes d'abord : un membre ou un groupe qui en a davantage
obtient les statistiques de ses 10 000 dernières, et `truncated` le dit ; une période lit une saison antérieure. Les
joueurs qu'un résultat nomme sont au plus 50. Le travail croît avec les parties lues, jamais avec les parties que le
stockage contient : un stockage répond à une requête par un index sur le membre, le groupe et la date.
