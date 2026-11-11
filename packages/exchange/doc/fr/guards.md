<!-- phil-dev source-sha256=589d4b47297c6f1bcfeab4c78191eb7ef107c74652b6a84e7f890f15d4d1bfad -->
# Gardes

Ce que les membres se donnent (transferts de points, échanges d'objets) passe par des `Guards`, une pour chacun :

| Garde | Règle | Refus |
|---|---|---|
| Échange avec soi-même | Un membre n'échange jamais avec lui-même ; toujours active. | `Refusal::SelfExchange` |
| Nouveaux comptes | Les deux membres ont adhéré au moins le délai avant maintenant. | `Refusal::NewMember` |
| Allocation | Ce qu'un membre donne sur une période reste dans la limite : des points pour les transferts, des offres pour les échanges. | `Refusal::AllowanceReached` |

```php
use Tilttek\Exchange\Allowance;
use Tilttek\Exchange\Guards;
use Tilttek\Points\Rule\Period;

$transferGuards = new Guards(new Allowance(500, Period::Week), 'P7D', 'America/Toronto', $memberSince);
$tradeGuards = new Guards(new Allowance(10, Period::Day), timezone: 'America/Toronto');
```

Les périodes sont celles des plafonds de gains de `tilttek/points` : un jour civil, une semaine ISO à partir du lundi,
un mois ou une année civils, ou depuis toujours, comptés dans le fuseau horaire des gardes. Les dates d'adhésion
viennent du `MemberSince` de l'application. Un membre qu'elle ne connaît pas est refusé tant qu'un délai est réglé, et
`NoMemberSince` ne connaît personne.

L'allocation est vérifiée selon ce que contiennent les stockages avant l'écriture de l'échange : deux échanges du même
membre au même instant peuvent tous deux passer. C'est une garde contre les abus, pas un solde ; le solde de points,
lui, ne devient jamais négatif.
