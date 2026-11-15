<!-- phil-dev source-sha256=1d5a43b0393e61c06dcce47835fc59741dd94d60ef184eda9b9de0cd466195e8 -->
# Utilisation

## Offrir une prime

Un membre offre une prime avec un `BountyDraft` : son identifiant, choisi par l'application — un ULID, un UUID —, le
sujet sur lequel elle est offerte, la raison, le montant et la fin.

```php
use Phil\Resource\ResourceReference;
use Tilttek\Bounties\BountyDraft;
use Tilttek\Bounties\BountyReason;
use Tilttek\Bounties\Prize;

$bounty = $bounties->offer(new BountyDraft(
    id: '01JD4',
    subject: ResourceReference::of('question', '42'),
    reason: BountyReason::Detail,
    prize: new Prize(100),                      // 100 points; new Prize(5, 'gems') for another unit of points
    endsAt: new DateTimeImmutable('+7 days'),   // required, within the site's rules
), $ana);
```

Les raisons sont `Attention`, `Detail`, `Sources`, `Reward` — une réponse déjà donnée la mérite —, `Creativity` et
`Other`. L'offre vérifie le brouillon selon les `BountyRules` du site — la durée, le plus petit et le plus grand
montant —, vérifie que le sujet n'a pas de prime ouverte et que le commanditaire a moins de primes ouvertes que les
règles le permettent — cinq par défaut, `openPerSponsor` —, détient les points pris sur ceux du commanditaire, conserve
la prime, puis répartit `BountyOffered`. Le même brouillon envoyé de nouveau par le même commanditaire rend la prime
qu'il a offerte et ne détient rien de plus ; le même identifiant avec un autre brouillon est refusé. Des points que le
commanditaire n'a pas lancent `InsufficientPoints`, et rien n'est offert.

## Réclamations

```php
$bounties->claim('01JD4', 'answer-901', $bea, 'answer:901');
```

Tout membre sauf le commanditaire réclame la prime jusqu'à sa fin : un identifiant de la réclamation, choisi par
l'application, et une référence opaque de ce qui y répond — une réponse, un message, un téléversement —, de 500 octets
au plus et sans espace. Un membre réclame cinq fois au plus, et une prime accepte 200 réclamations.

## Limites par membre

Chaque borne empêche un membre d'inonder les autres :

| Borne | Valeur par défaut | Où |
|---|---|---|
| Primes ouvertes par commanditaire | 5, de 1 à 100 | `BountyRules::$openPerSponsor` |
| Primes ouvertes par sujet | 1 | `BountyStore::openOn()` |
| Réclamations d'un membre sur une prime | 5 | `Bounty::MAX_CLAIMS_PER_MEMBER` |
| Réclamations sur une prime | 200 | `Bounty::MAX_CLAIMS` |
| Attributions sur une prime | 20 | `Bounty::MAX_AWARDS` |
| Durée d'une prime | 1 à 30 jours, 90 au plus | `BountyRules::$shortest`, `$longest` |
| Montant d'une prime | 10 à 10 000 points, 1 000 000 au plus | `BountyRules::$smallestPrize`, `$largestPrize` |

Les points du commanditaire bornent le reste : une prime est payée d'avance. Le nombre de primes ouvertes est lu avant
que l'offre soit conservée, donc des offres d'un même commanditaire envoyées au même instant peuvent toutes le passer ;
un site qui veut la borne exacte compte dans la même transaction que l'offre, dans son magasin.

## Attributions

```php
$bounties->award('01JD4', 'award-1', 'answer-901', 60, $ana);
$bounties->award('01JD4', 'award-2', 'answer-902', 30, $ana);
$bounties->close('01JD4', $ana);
```

Le commanditaire attribue des points aux réclamations jusqu'à la fin du délai de grâce — la fin et le délai de grâce des
règles, un jour par défaut — : le montant entier à une réclamation, ou partagé entre plusieurs, 20 attributions au plus,
jamais plus que ce qui reste. Chaque attribution a un identifiant : envoyée de nouveau, elle ne change rien. Une fois
le montant entier attribué, la prime est réglée aussitôt.

## Comment une prime est réglée

| Mouvement | Par | Quand | Les points |
|---|---|---|---|
| Le montant entier attribué | Le commanditaire | Avant la fin du délai de grâce | Payés aux gagnants |
| `close()` | Le commanditaire | Avec au moins une attribution, avant la fin du délai de grâce | Les attributions payées, le reste rendu |
| `cancel()` | Le commanditaire | Avant toute réclamation | Rendus |
| `expire()`, `expireDue()` | Une tâche planifiée | Une fois le délai de grâce passé | Les attributions payées et le reste rendu ; tout rendu quand rien n'a été attribué |

```text
open ──► awarded
  └───► expired | cancelled
```

Une prime qui a des réclamations ne s'annule pas : elle est attribuée, ou elle expire. Les attributions à un même
membre sont payées ensemble, en un seul paiement. Une prime réglée ne change plus jamais, et son sujet est libre pour
une nouvelle prime. Chaque règlement répartit un `BountyAwarded` pour chaque gagnant et un `BountyClosed`, après son
mouvement de séquestre.

## Lire une prime

`get($id)` rend le `Bounty` : son état, ses réclamations et ses attributions, `awarded()` et `payouts()`.
`Bounty::toArray()` rend l'enregistrement que conserve une application, et `Bounty::fromArray()` le relit, vérifié champ
par champ et dans son ensemble : un enregistrement qu'aucun mouvement n'aurait pu écrire est refusé avec
`InvalidBounty`.

## Heures

Toute heure est conservée en UTC à la microseconde près, quel que soit le fuseau horaire de l'horloge ou du brouillon.
La bibliothèque ordonne les heures avec `<` et `>=`, et ne compare deux heures que par ce qu'elles écrivent, jamais avec
`==` ni `===`.
