<!-- phil-dev source-sha256=15b44a12023d78bb4c2ac9eef357216ef8134713da0eef66dbf8eb6806f8a654 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Raffle\Exception\RaffleException`.

| Exception | Lancée quand | Message, par exemple |
|---|---|---|
| `InvalidRaffle` | Un identifiant, un participant, un nombre de gagnants, une marge, une chaîne de balise, une ronde, un relais ou un quorum est refusé, ou un tirage ou une preuve ne se lit pas | `An entrant is an opaque reference of 1 to 128 letters, digits, dots, colons, underscores, tildes or hyphens.` |
| `RaffleRefused` | Un tirage inconnu ou ouvert deux fois, un prix dont le germe vient de l'organisateur, aucune balise, une inscription une fois les inscriptions fermées, en double ou au-delà de la limite, un tirage scellé sans participants, tiré avant d'être scellé, deux fois ou avant sa ronde, une preuve avant le tirage | `member:1042 has entered the raffle door-prize already: an entrant holds one chance, never more.` |
| `RaffleConflict` | Un changement fait à partir d'une version que le dépôt de stockage ne détient plus, ou des participants qui n'ont plus pour empreinte celle qui a été annoncée | `The raffle door-prize was changed from version 4, but the store holds version 5.` |
| `BeaconUnavailable` | Moins de relais ont répondu que le quorum : la ronde n'est pas encore publiée, ou ils sont injoignables | `The beacon gave 1 answer(s) for round 1234567, 2 needed: the round is not published yet, or the relays are unreachable.` |
| `BeaconRefused` | La réponse d'un relais ne se vérifie pas, des relais sont en désaccord, une signature ne se vérifie pas, ou la balise a donné une autre ronde | `The relays give different signatures for round 1234567.` |

Une `BeaconUnavailable` ou une `BeaconRefused` lancée par une lecture des relais de drand porte son `Beacon\RelayReport`
dans `$report` : ce que chaque relais a donné. Elle est nulle autrement.

`InvalidRaffle` étend `InvalidArgumentException`, `RaffleRefused` étend `DomainException`, et les autres étendent
`RuntimeException`.
