# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Referrals` | Gives a member's code, records a referral, pays its reward once, reads referrals, erases a member |
| `ReferralCode` | The random code a member shares, in Crockford's base 32 |
| `ReferralId` | The random identifier of a referral, which the keys of its points carry |
| `Referral` | A newcomer referred by a member: status, moments and the points each side received |
| `ReferralStatus` | Pending, rewarded or refused |
| `Refusal` | Why a referral or its reward is refused |
| `Reward` | What rewarding did: the referral, the points of each side, and whether this call wrote them |
| `Terms` | The points, the cap, the currency, the source and the guards on dates |
| `FirstAction` | The newcomer's first real action: its kind and its moment |
| `Erasure` | What erasing a member did; the `erased:member` reference |
| `Contract\ReferralStore` | Where the codes and the referrals are kept |
| `Contract\FirstActions` | Tells a member's first real action |
| `Contract\SamePerson` | Tells whether two accounts belong to one person |
| `Contract\NoSamePersonSignal` | Two references are two people |
| `Store\InMemoryReferralStore` | The store in memory, for tests |
| `Exception\ReferralsException` and its four exceptions | [Exceptions](exceptions.md) |
