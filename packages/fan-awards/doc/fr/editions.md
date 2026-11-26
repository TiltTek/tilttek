<!-- phil-dev source-sha256=72b1312706627e44aca870882e39bafe031ac9226b2644bfa26f759d56f91223 -->
# Éditions et phases

Une édition est une année des prix, ou celle d'un congrès : sa clé, son nom, ses catégories, et si elle compte une
demi-finale. `FanAwards` la gère sur un `Contract\AwardStore`.

## Catégories

```php
new Category(
    'novel',            // 1 to 64 lower-case letters, digits, "_" or "-", starting with a letter
    'Best novel',       // 1 to 100 characters
    nominations: 5,     // nominees on a nomination ballot, 1 to 10
    semifinalists: 10,  // nominees the semifinal takes, 2 to 20
    finalists: 5,       // nominees the final takes, 2 to the semifinalists
    method: 'instant_runoff', // the ranked method of the final: instant_runoff, borda, schulze
    noAward: true,      // "no award" among the final's options
);
```

Une édition compte de 1 à 50 catégories aux clés uniques. `create()` refuse une catégorie dont la méthode n'est pas une
méthode classée des `CountingMethods` de l'application, et une clé qu'une autre édition possède.

## Candidats

Un `Nominee` a une clé — l'option des bulletins, 1 à 64 lettres ASCII, chiffres, `_` ou `-` —, un titre de 1 à 200
caractères et, s'il y en a une, la référence de l'application de ce qu'il désigne — `book:12`. La clé `no-award` est
réservée à « aucun prix ». L'application tire la clé de son catalogue, de sorte que deux votants qui ajoutent la même
œuvre donnent la même clé ; le premier titre donné est conservé.

## Les phases

| Phase | Ce qui se passe | `advance()` mène à |
|---|---|---|
| `Draft` | L'édition se prépare ; les organisateurs font des `propose()` de candidats | `Nomination` |
| `Nomination` | Les organisateurs proposent encore ; les votants proposent des candidats, en ajoutant des candidats | `Semifinal`, ou `Final` sans demi-finale |
| `Semifinal` | Les votants choisissent un candidat de la présélection, ou en ajoutent un | `Final` |
| `Final` | Les votants classent les finalistes et « aucun prix » | `Counted` |
| `Counted` | Les organisateurs font un `preview()` des résultats | `Announced` |
| `Announced` | Tout le monde lit `results()` | — |

Avancer depuis une phase de vote dépouille chacune de ses catégories, comme le décrit
[dépouillement et résultats](counting.md). Avancer une édition annoncée est refusé avec `Refusal::WrongPhase`.
L'application décide quand chaque phase se termine — à la main, ou depuis son planificateur.

## Voter

```php
$awards->vote('2027', 'novel', $voter, ['the-long-road', new Nominee('night-ferry', 'Night Ferry')]);
$awards->ballot('2027', 'novel', $voter);   // the voter's own ballot of the current phase
```

| Phase | Un bulletin contient | Ajouts |
|---|---|---|
| `Nomination` | 1 au nombre de `nominations` de la catégorie, chaque candidat une fois | Oui |
| `Semifinal` | Exactement un candidat | Oui |
| `Final` | Un classement de 1 à toutes les options, les clés seulement, `no-award` parmi elles quand la catégorie l'offre | Non |

Un votant dépose un bulletin par catégorie et par phase ; voter de nouveau le remplace jusqu'à la fin de la phase. Un
bulletin hors d'une phase de vote est refusé avec `Refusal::WrongPhase`, un votant que la `VoterEligibility` de
l'application écarte avec `Refusal::VoterNotEligible`, et un bulletin qui enfreint les règles de la phase avec
`InvalidAward`, qui dit comment. `finalOptions()` liste les options de la finale dans leur ordre.

Tant qu'une catégorie n'offre aucun candidat ou un seul, un bulletin de candidature ou de demi-finale contient ce
candidat seul, ou un candidat ajouté : les premiers candidats d'une catégorie ouverte arrivent un à un. Une finale de
moins de deux options ne prend aucun bulletin.

## Ajouts et admissibilité

Un `Nominee` dans un bulletin, dont la clé n'est pas encore un candidat, est un ajout : la `NomineeEligibility` de
l'application le vérifie d'abord, et un refus est lancé comme `Refusal::Ineligible` avec la raison donnée, pour que le
votant la lise. Un candidat qu'un organisateur propose est vérifié de la même façon. Une catégorie offre au plus 100
candidats dans une phase, le maximum qu'un bulletin de `phil/poll` contient ; au-delà, `Refusal::TooManyCandidates`.
Un ajout ne rejoint les candidats que si son bulletin est valide.

## Votants

`VoterEligibility::mayVote($voter, $edition)` dit qui vote : un membre depuis avant les candidatures, un membre de la
communauté à laquelle les prix appartiennent. Ce n'est jamais un paiement ni un achat. `Eligibility\AnyVoter`, la
valeur par défaut, laisse entrer tous les membres que l'application laisse accéder au bulletin.
