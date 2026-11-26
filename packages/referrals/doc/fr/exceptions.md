<!-- phil-dev source-sha256=49ad4e7a5d58c6713fbfa968a6047499dea2077a5a9e39b4e675a7a8928a8f9f -->
# Exceptions

Toutes les exceptions de la bibliothèque implémentent `Exception\ReferralsException` : attrapez celle-ci pour les
attraper toutes.

| Exception | Levée quand | Message |
|---|---|---|
| `ReferralRefused` | Un garde-fou refuse le parrainage ou sa récompense ; `refusal` dit pourquoi | `A member cannot refer themselves.`, `The referrer has not been a member long enough to refer others.`, `Only a member who has just joined can be referred.`, `This member was already referred by another member.`, `The referred member has not made a first real action since the referral.` |
| `UnknownReferralCode` | Le code n'appartient à aucun membre | `The referral code belongs to no member.`, sans le code |
| `ReferralConflict` | Le magasin a refusé chacun des cinq codes tirés pour un membre | `The 5 referral codes drawn all belong to other members.` |
| `InvalidReferral` | Des conditions, un code, un identifiant, un genre d'action, un parrainage ou une lecture hors limites | Ce qui est hors limites, et la limite |

`ReferralRefused` et `UnknownReferralCode` étendent `DomainException` ; `ReferralConflict` étend `RuntimeException` ;
`InvalidReferral` étend `InvalidArgumentException`.

Les exceptions de `tilttek/points` parviennent telles quelles à l'appelant : `MovementConflict` quand une clé d'un
parrainage est écrite dans le grand livre pour d'autres points.
