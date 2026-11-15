<!-- phil-dev source-sha256=50b7a687ccbaa3959e0436fc3fcda51fe18f29c5a4b2c81100bc7ac59e2fa0b5 -->
# Utilisation

## Créer un défi

Un membre demande un défi avec un `ChallengeDraft` : son identifiant, choisi par l'application — un ULID, un UUID —, son
titre de 120 caractères au plus, son lot, sa fin, les amis qu'il invite, qui peut y participer, qui juge ses preuves,
ses conditions de 2 000 caractères au plus et, pour un vote, les votes nécessaires.

```php
use Tilttek\FriendChallenges\ChallengeDraft;
use Tilttek\FriendChallenges\Prize;
use Tilttek\FriendChallenges\Validation;
use Tilttek\FriendChallenges\Visibility;

$challenge = $challenges->create(new ChallengeDraft(
    id: '01JD3',
    title: 'Paint ten minis in a week',
    prize: new Prize(100),                       // 100 points; new Prize(5, 'gems') for another unit of points
    endsAt: new DateTimeImmutable('+7 days'),
    invitees: [$bea, $cy, $dee],
    visibility: Visibility::Friends,             // the default; Visibility::Open lets any member take part
    validation: Validation::Vote,                // or Validation::Creator, the default, or Validation::Moderator
    description: "Ten minis.\nOne photo of each.",
    votesNeeded: 2,
), $ana);
```

La création vérifie le brouillon selon les `ChallengeRules` du site — la durée du défi, le plus gros lot —, vérifie que
chaque invité est un ami du créateur par `Contract\Friendship`, détient le lot pris sur les points du créateur,
conserve le défi, puis répartit `ChallengeCreated` et un `MemberInvited` pour chaque ami. Le même brouillon envoyé de
nouveau par le même créateur rend le défi qu'il a créé et ne détient rien de plus ; le même identifiant avec un autre
brouillon est refusé. Un lot que les points du créateur ne couvrent pas lance `InsufficientPoints`, et rien n'est créé.

Un défi entre amis en invite de 1 à 50 ; un défi ouvert n'invite personne en particulier. Le créateur établit et juge
le défi, et n'y participe jamais.

## Participer

```php
$challenges->join('01JD3', $bea);
$challenges->decline('01JD3', $dee);
```

Un ami invité — ou n'importe quel membre, dans un défi ouvert — participe jusqu'à la fin du défi ; un défi accepte 100
participants au plus. Un ami invité peut décliner ; le défi garde qui a décliné, et une application montre au créateur
qui participe, jamais qui a décliné. Un ami qui a décliné peut encore participer avant la fin.

## Les preuves et leur examen

```php
$challenges->submitProof('01JD3', 'proof-1', $bea, 'upload:8812');
$challenges->approve('01JD3', 'proof-1', $ana);
$challenges->reject('01JD3', 'proof-2', $ana);
```

Un participant envoie une preuve jusqu'à la fin du défi : un identifiant de la preuve, choisi par l'application, et une
référence opaque du justificatif que conserve l'application — un téléversement, un journal de partie, un message —, de
500 octets au plus et sans espace. Un participant envoie cinq preuves au plus, et un défi en contient 200.

Les preuves sont examinées jusqu'à `reviewEndsAt`, la fin du défi plus l'examen que donnent les règles, trois jours par
défaut. Qui les examine dépend de sa `Validation` :

| Validation | Qui approuve ou rejette | Quand une preuve est acceptée |
|---|---|---|
| `Creator` | Le créateur | À l'approbation du créateur |
| `Moderator` | Un modérateur du site, comme le dit `Contract\Moderation::mayModerate()` | À l'approbation d'un modérateur |
| `Vote` | Le créateur et les participants, chacun sur les preuves des autres | Quand les votes nécessaires l'approuvent ; rejetée quand les votes nécessaires la rejettent |

Un votant vote une fois sur une preuve et ne change pas son vote. La première preuve acceptée gagne : son participant
est le gagnant, le lot lui est payé, et `ChallengeWon` est réparti.

## Comment un défi se termine

| Mouvement | Par | Quand | Le lot |
|---|---|---|---|
| Une preuve acceptée | Son juge | Avant la fin de l'examen | Payé au gagnant |
| `cancel()` | Le créateur | Avant l'envoi de toute preuve | Rendu au créateur |
| `expire()`, `expireDue()` | Une tâche planifiée | Une fois l'examen fini, sans preuve acceptée | Rendu au créateur |
| `remove()` | Un modérateur | Tant que le défi est ouvert ou gelé | Rendu au créateur |

```text
open ──► won
 │ ▲  └─► cancelled | expired
 ▼ │
frozen ──► removed ◄── open
```

Un défi réglé ne change plus jamais. Chaque mouvement qui termine un défi répartit `ChallengeWon` ou `ChallengeEnded`,
après son mouvement de séquestre.

## Lire un défi

`get($id)` rend le `Challenge` : son état, ses participants, ses preuves avec leur état et leurs votes, son gagnant et
le moment de son règlement. `Challenge::toArray()` rend l'enregistrement que conserve une application, et
`Challenge::fromArray()` le relit, vérifié champ par champ et dans son ensemble : un enregistrement qu'aucun mouvement
n'aurait pu écrire est refusé avec `InvalidChallenge`.

## Heures

Toute heure est conservée en UTC à la microseconde près, quel que soit le fuseau horaire de l'horloge ou du brouillon.
La bibliothèque ordonne les heures avec `<` et `>=`, et ne compare deux heures que par ce qu'elles écrivent, jamais avec
`==` ni `===`.
