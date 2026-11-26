# Exceptions

Every exception of the library implements `Exception\ReferralsException`: catch this one to catch them all.

| Exception | Thrown when | Message |
|---|---|---|
| `ReferralRefused` | A guard refuses the referral or its reward; `refusal` says why | `A member cannot refer themselves.`, `The referrer has not been a member long enough to refer others.`, `Only a member who has just joined can be referred.`, `This member was already referred by another member.`, `The referred member has not made a first real action since the referral.` |
| `UnknownReferralCode` | The code belongs to no member | `The referral code belongs to no member.`, without the code |
| `ReferralConflict` | The store refused each of the five codes drawn for a member | `The 5 referral codes drawn all belong to other members.` |
| `InvalidReferral` | Terms, a code, an identifier, an action kind, a referral or a read out of bounds | What is out of bounds, and the bound |

`ReferralRefused` and `UnknownReferralCode` extend `DomainException`; `ReferralConflict` extends `RuntimeException`;
`InvalidReferral` extends `InvalidArgumentException`.

The exceptions of `tilttek/points` reach the caller unchanged: `MovementConflict` when a key of a referral is written
in the ledger for other points.
