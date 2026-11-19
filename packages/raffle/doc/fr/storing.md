<!-- phil-dev source-sha256=99a155ae086d77ea9c56fdd1d9ccb07ac5b1e3bc759e49b78277b984af1ab561 -->
# Stockage

`Contract\RaffleStore` garde les tirages et leurs participants :

| Méthode | Fait |
|---|---|
| `find($raffle)` | Le tirage, ou null |
| `add($raffle)` | Garde un tirage nouvellement ouvert, à la version 1 ; `RaffleConflict` quand l'identifiant est pris |
| `save($raffle)` | Garde la version suivante du tirage |
| `enter($raffle, $entrant)` | Ajoute le participant et garde la version suivante du tirage, les deux ou aucun ; faux, et rien de gardé, quand le participant est déjà inscrit |
| `entrants($raffle)` | Les participants, dans un ordre quelconque |

Chaque changement stocke la version suivante du tirage et est refusé avec `RaffleConflict` quand la version stockée
n'est pas celle dont il est parti : une inscription qui arrive pendant la fermeture des inscriptions ne glisse jamais
sous l'empreinte des participants, puisque la fermeture et l'inscription ne peuvent pas aboutir toutes deux.
L'appelant relit le tirage et recommence. Sur une base de données, la ligne du tirage porte sa version, mise à jour par
`WHERE version = ?`, et les participants ont une clé unique sur le tirage et le participant, écrite dans la même
transaction.

`Raffle::toArray()` et `Raffle::fromArray()` donnent les valeurs simples à garder, relues champ par champ. Un tirage
dont le germe vient d'un engagement-révélation contient ce germe : quiconque le lit connaît les gagnants dès la
fermeture des inscriptions. Gardez-le à part de ce que le public lit, chiffré au repos quand le stockage est partagé.

`Raffles` vérifie, quand il tire et quand il donne une preuve, que les participants gardés ont toujours pour empreinte
celle qui a été annoncée, et lance `RaffleConflict` sinon. `Store\InMemoryRaffleStore` garde tout dans le processus,
pour les tests et les scripts.
