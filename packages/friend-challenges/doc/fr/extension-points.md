<!-- phil-dev source-sha256=43f523ee24f76eb640eed5afec25f2c0c8bd65fee52a51e0e059b533555ebf2c -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que donne une application | Fourni |
|---|---|---|
| `Contract\ChallengeStore` | Où les défis sont conservés : une table d'enregistrements, un magasin de documents | `Store\InMemoryChallengeStore` |
| `Contract\PrizeEscrow` | Où les lots sont détenus : voir [Séquestre](escrow.md) | `Escrow\PointsPrizeEscrow`, `Escrow\InMemoryPrizeEscrow` |
| `Contract\Friendship` | Qui est l'ami de qui, d'après le graphe social du site | `Store\InMemoryFriendship` |
| `Contract\Moderation` | Où vont les signalements, et qui modère : voir [Modération](moderation.md) | `Store\InMemoryModeration` |
| `ChallengeRules` | La durée d'un défi, son examen, son plus gros lot | ses valeurs par défaut |
| `Psr\EventDispatcher\EventDispatcherInterface` | Où vont les événements | aucun : rien n'est réparti |
| `Psr\Clock\ClockInterface` | L'heure de chaque mouvement | `Symfony\Component\Clock\NativeClock` |

Chaque point est passé au constructeur de `FriendChallenges`, qui ne conserve rien entre deux appels.

## Écrire un magasin

Un `ChallengeStore` conserve un enregistrement par défi, `Challenge::toArray()`, et le rend par
`Challenge::fromArray()`. Ce que fait chaque implémentation :

- `get($id)` rend le défi, ou null.
- `add($challenge)` conserve un nouveau défi, et lance `ChallengeConflict` quand un défi du même identifiant est déjà
  conservé.
- `update($challenge, $expected)` conserve le défi à sa version suivante si et seulement si celui qui est conservé est
  encore à la version `$expected`, sinon lance `ChallengeConflict` et n'écrit rien. Dans une base de données, un
  `UPDATE … WHERE id = ? AND version = ?` qui compte les lignes qu'il a changées donne exactement cela.
- `due($at, $limit)` liste les identifiants des défis ouverts dont `review_ends_at` est à `$at` ou avant, les plus
  anciens d'abord, `$limit` au plus ; un index sur l'état et `review_ends_at` y répond.
- `involving($member, $limit)` liste les identifiants des défis qui nomment le membre — voir `Challenge::involves()` : le
  créateur, les invités et les participants —, `$limit` au plus, pour son [effacement](privacy.md). Une table des
  membres de chaque défi, écrite avec l'enregistrement, y répond.

Conservez les heures de l'enregistrement comme les textes UTC qu'il contient, ou dans des colonnes UTC. Quand les défis
et le séquestre vivent dans la même base de données, conservez la création d'un défi et sa détention dans une seule
transaction.

## Les événements

Chaque événement est réparti une fois son mouvement conservé et réglé, jamais pour un mouvement refait :

| Événement | Quand | Porte |
|---|---|---|
| `Event\ChallengeCreated` | Un défi est créé et son lot détenu | Le défi, son créateur |
| `Event\MemberInvited` | Une fois pour chaque ami invité, à la création | Le défi, le membre |
| `Event\ParticipantJoined` | Un membre participe | Le défi, le membre |
| `Event\ProofSubmitted` | Un participant envoie une preuve | Le défi, la preuve, le participant |
| `Event\ProofDecided` | Une preuve est acceptée, rejetée ou retirée | Le défi, la preuve, son état |
| `Event\ChallengeWon` | Une preuve est acceptée et le lot payé | Le défi, le gagnant, la preuve, le lot, son unité |
| `Event\ChallengeEnded` | Un défi est annulé, expire ou est retiré, et son lot rendu | Le défi, son état |
| `Event\ChallengeFrozen` | Un modérateur gèle un défi | Le défi |
| `Event\ChallengeUnfrozen` | Un modérateur dégèle un défi | Le défi |

Les événements ne portent que des identifiants. Un écouteur qui avise un membre, pousse la page par Mercure ou envoie un
message de Messenger envoie ces identifiants, et lit le reste dans le magasin.
