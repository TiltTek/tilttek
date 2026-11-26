<!-- phil-dev source-sha256=269d1e2c7352809509a5f22110828c6fc57b997dd155c9f9296b553ce9d5a9f3 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Referrals` | Donne le code d'un membre, enregistre un parrainage, verse sa récompense une fois, lit les parrainages, efface un membre |
| `ReferralCode` | Le code aléatoire qu'un membre partage, en base 32 de Crockford |
| `ReferralId` | L'identifiant aléatoire d'un parrainage, que portent les clés de ses points |
| `Referral` | Un nouveau parrainé par un membre : statut, moments et points que chaque côté a reçus |
| `ReferralStatus` | En attente, récompensé ou refusé |
| `Refusal` | Pourquoi un parrainage ou sa récompense est refusé |
| `Reward` | Ce qu'a fait la récompense : le parrainage, les points de chaque côté, et si cet appel les a écrits |
| `Terms` | Les points, le plafond, la devise, la source et les garde-fous sur les dates |
| `FirstAction` | La première vraie action du nouveau : son genre et son moment |
| `Erasure` | Ce qu'a fait l'effacement d'un membre ; la référence `erased:member` |
| `Contract\ReferralStore` | L'endroit où les codes et les parrainages sont conservés |
| `Contract\FirstActions` | Indique la première vraie action d'un membre |
| `Contract\SamePerson` | Indique si deux comptes appartiennent à une même personne |
| `Contract\NoSamePersonSignal` | Deux références sont deux personnes |
| `Store\InMemoryReferralStore` | Le magasin en mémoire, pour les tests |
| `Exception\ReferralsException` et ses quatre exceptions | [Exceptions](exceptions.md) |
