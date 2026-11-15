<!-- phil-dev source-sha256=4cf64c4da99dd33e25905e24ec7f1b093a6e1ef51e9f527cf1d416997773d0df -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Fourni |
|---|---|---|
| `Contract\BountyStore` | Où les primes sont conservées : une table d'enregistrements, un magasin de documents | `Store\InMemoryBountyStore` |
| `Contract\BountyEscrow` | Où les points sont détenus : voir [Séquestre](escrow.md) | `Escrow\PointsBountyEscrow`, `Escrow\InMemoryBountyEscrow` |
| `BountyRules` | La durée, le délai de grâce, la plus petite et la plus grande prime, les primes ouvertes par commanditaire | ses valeurs par défaut |
| `Psr\EventDispatcher\EventDispatcherInterface` | Où vont les événements | aucun : rien n'est réparti |
| `Psr\Clock\ClockInterface` | L'heure de chaque mouvement | `Symfony\Component\Clock\NativeClock` |

Chaque point est passé au constructeur de `Bounties`, qui ne conserve rien entre deux appels.

## Écrire un magasin

Un `BountyStore` conserve un enregistrement par prime, `Bounty::toArray()`, et le rend par `Bounty::fromArray()`. Ce que
fait chaque implémentation :

- `get($id)` rend la prime, ou null.
- `openOn($subject)` rend l'identifiant de la prime ouverte sur le sujet, ou null.
- `openBy($sponsor)` compte les primes ouvertes que commandite le membre ; un index sur le commanditaire et l'état y
  répond.
- `add($bounty)` conserve une nouvelle prime, et lance `BountyConflict` quand une prime du même identifiant, ou une
  prime ouverte sur le même sujet, est déjà conservée. Dans une base de données, une clé unique sur l'identifiant et un
  index partiel unique sur le sujet des primes ouvertes donnent exactement cela.
- `update($bounty, $expected)` conserve la prime à sa version suivante si et seulement si celle qui est conservée est
  encore à la version `$expected`, sinon lance `BountyConflict` et n'écrit rien : un `UPDATE … WHERE id = ? AND version =
  ?` qui compte les lignes qu'il a changées.
- `due($at, $limit)` liste les identifiants des primes ouvertes dont `grace_ends_at` est à `$at` ou avant, les plus
  anciennes d'abord, `$limit` au plus ; un index sur l'état et `grace_ends_at` y répond.
- `involving($member, $limit)` liste les identifiants des primes qui nomment le membre — voir `Bounty::involves()` : le
  commanditaire et les réclamants —, `$limit` au plus, pour son [effacement](privacy.md). Une table des membres de
  chaque prime, écrite avec l'enregistrement, y répond.

Conservez les heures de l'enregistrement comme les textes UTC qu'il contient, ou dans des colonnes UTC. Quand les primes
et le séquestre vivent dans la même base de données, conservez l'offre d'une prime et sa détention dans une seule
transaction.

## Les événements

Chaque événement est réparti une fois son mouvement conservé et réglé, jamais pour un mouvement refait :

| Événement | Quand | Porte |
|---|---|---|
| `Event\BountyOffered` | Une prime est offerte et ses points détenus | La prime, son commanditaire, son sujet |
| `Event\BountyClaimed` | Un membre réclame une prime | La prime, la réclamation, le réclamant |
| `Event\AwardGiven` | Le commanditaire attribue des points à une réclamation | La prime, l'attribution, la réclamation, les points |
| `Event\BountyAwarded` | Une fois pour chaque gagnant, quand la prime est réglée et les points payés | La prime, le gagnant, les points, leur unité |
| `Event\BountyClosed` | Une prime est réglée — attribuée, expirée ou annulée | La prime, son état, les points rendus |

Les événements ne portent que des identifiants. Un écouteur qui avise un membre, pousse la page par Mercure ou envoie un
message de Messenger envoie ces identifiants, et lit le reste dans le magasin.
